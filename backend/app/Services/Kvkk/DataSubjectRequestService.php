<?php

namespace App\Services\Kvkk;

use App\Enums\DataSubjectRequestChannel;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use App\Enums\DataSubjectType;
use App\Models\DataProcessingActivity;
use App\Models\DataSubjectRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\Settings\Settings;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * D2b — Veri sahibi talebi yaşam döngüsü.
 */
class DataSubjectRequestService
{
    public function __construct(
        protected WorkflowService $workflows,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $companyId, array $data, ?User $actor = null): DataSubjectRequest
    {
        $types = array_values(array_unique(array_map('strval', $data['request_types'] ?? [])));
        $allowed = DataSubjectRequestType::values();
        foreach ($types as $t) {
            if (! in_array($t, $allowed, true)) {
                throw ValidationException::withMessages([
                    'request_types' => ["Geçersiz talep türü: {$t}"],
                ]);
            }
        }
        if ($types === []) {
            throw ValidationException::withMessages([
                'request_types' => ['En az bir talep türü seçilmelidir.'],
            ]);
        }

        $channel = DataSubjectRequestChannel::from((string) $data['channel']);
        $dueDays = $this->resolveDueDays($companyId);
        $identityVerified = (bool) ($data['identity_verified'] ?? false);
        $verificationMethod = $data['verification_method'] ?? null;

        // Portal: oturum kimliği = doğrulanmış
        if ($channel === DataSubjectRequestChannel::Portal && $actor) {
            $identityVerified = true;
            $verificationMethod = 'portal_session';
        }

        $status = $identityVerified
            ? DataSubjectRequestStatus::New
            : DataSubjectRequestStatus::IdentityPending;

        return DB::transaction(function () use ($companyId, $data, $actor, $types, $channel, $dueDays, $identityVerified, $verificationMethod, $status) {
            $request = DataSubjectRequest::query()->create([
                'company_id' => $companyId,
                'subject_type' => DataSubjectType::from((string) $data['subject_type']),
                'subject_id' => isset($data['subject_id']) ? (int) $data['subject_id'] : null,
                'applicant_name' => (string) $data['applicant_name'],
                'contact' => (string) $data['contact'],
                'request_types' => $types,
                'description' => $data['description'] ?? null,
                'channel' => $channel,
                'identity_verified' => $identityVerified,
                'verification_method' => $verificationMethod,
                'verified_by' => $identityVerified && $actor ? $actor->id : null,
                'verified_at' => $identityVerified ? now() : null,
                'status' => $status,
                'due_date' => now()->addDays($dueDays)->toDateString(),
                'assigned_to' => $data['assigned_to'] ?? null,
                'email_verify_token' => $channel === DataSubjectRequestChannel::PublicForm
                    ? Str::random(48)
                    : null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            if ($identityVerified && $status === DataSubjectRequestStatus::New) {
                $this->startReviewWorkflow($request);
            }

            return $request->fresh();
        });
    }

    public function verifyIdentity(DataSubjectRequest $request, User $actor, string $method): DataSubjectRequest
    {
        $request->forceFill([
            'identity_verified' => true,
            'verification_method' => $method,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'status' => DataSubjectRequestStatus::InReview,
            'updated_by' => $actor->id,
        ])->save();

        $this->startReviewWorkflow($request->fresh());

        return $request->fresh();
    }

    public function verifyEmailToken(DataSubjectRequest $request, string $token): DataSubjectRequest
    {
        if (! $request->email_verify_token || ! hash_equals($request->email_verify_token, $token)) {
            throw ValidationException::withMessages([
                'token' => ['E-posta doğrulama bağlantısı geçersiz.'],
            ]);
        }

        $request->forceFill([
            'email_verified_at' => now(),
            'identity_verified' => false, // İK hâlâ manuel doğrular (dış kanal)
            'status' => DataSubjectRequestStatus::IdentityPending,
        ])->save();

        return $request->fresh();
    }

    /**
     * Silme talebi onayında veri SİLİNMEZ — destruction_pending işaretlenir.
     *
     * @param  'accept'|'partial'|'reject'  $template
     */
    public function respond(
        DataSubjectRequest $request,
        User $actor,
        string $template,
        string $body,
        ?string $rejectionReason = null,
    ): DataSubjectRequest {
        $hasSilme = in_array(DataSubjectRequestType::Silme->value, $request->request_types ?? [], true);
        $destructionPending = false;
        $destructionScope = null;

        if ($hasSilme && in_array($template, ['accept', 'partial'], true)) {
            $destructionPending = true;
            $destructionScope = $this->buildDestructionScope((int) $request->company_id);
        }

        $status = match ($template) {
            'reject' => DataSubjectRequestStatus::Rejected,
            'partial' => DataSubjectRequestStatus::PartiallyApproved,
            default => DataSubjectRequestStatus::Approved,
        };

        $request->forceFill([
            'response_template' => $template,
            'response_body' => $body,
            'rejection_reason' => $rejectionReason,
            'responded_at' => now(),
            'status' => DataSubjectRequestStatus::Completed,
            'destruction_pending' => $destructionPending,
            'destruction_scope' => $destructionScope,
            'updated_by' => $actor->id,
        ])->save();

        // response PDF/HTML dosyası ayrı serviste yazılır
        return $request->fresh();
    }

    public function resolveDueDays(int $companyId): int
    {
        $days = (int) Settings::get('kvkk.data_subject.response_days', ['company_id' => $companyId]);
        $legalMax = (int) Settings::get('legal.kvkk.response_days.max', []);
        if ($legalMax < 1) {
            $legalMax = 30;
        }
        if ($days < 1) {
            $days = 30;
        }
        if ($days > $legalMax) {
            $days = $legalMax;
        }

        return $days;
    }

    /**
     * @return array{retained: list<array<string, mixed>>, pending_destruction: list<string>}
     */
    public function buildDestructionScope(int $companyId): array
    {
        $activities = DataProcessingActivity::query()
            ->where('company_id', $companyId)
            ->get();

        $retained = [];
        foreach ($activities as $act) {
            if ($act->retention_period_months) {
                $retained[] = [
                    'activity_key' => $act->key,
                    'name' => $act->name,
                    'legal_basis' => $act->legal_basis,
                    'retention_period_months' => $act->retention_period_months,
                    'reason' => 'Yasal saklama yükümlülüğü',
                ];
            }
        }

        return [
            'retained' => $retained,
            'pending_destruction' => ['eligible_personal_data'], // D2c işler
            'note' => 'Bu dalgada veri silinmedi; imha bekliyor.',
        ];
    }

    public function resolveSubjectFromPortalUser(User $user): array
    {
        $emp = Employee::query()
            ->where('company_id', $user->home_company_id)
            ->where('user_id', $user->id)
            ->first();

        return [
            'subject_type' => DataSubjectType::Employee->value,
            'subject_id' => $emp ? (int) $emp->id : (int) $user->id,
            'applicant_name' => $user->name,
            'contact' => $user->email,
        ];
    }

    private function startReviewWorkflow(DataSubjectRequest $request): void
    {
        $request->forceFill(['status' => DataSubjectRequestStatus::InReview])->save();
        $this->workflows->startWorkflow($request, [
            'request_types' => $request->request_types,
        ]);
    }
}
