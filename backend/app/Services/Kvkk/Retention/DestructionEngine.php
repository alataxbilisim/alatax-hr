<?php

namespace App\Services\Kvkk\Retention;

use App\Enums\DestructionCandidateStatus;
use App\Enums\RetentionDecisionType;
use App\Enums\RetentionStrategy;
use App\Enums\RetentionTriggerEvent;
use App\Models\DestructionApproval;
use App\Models\DestructionCandidate;
use App\Models\DestructionLog;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\LegalHold;
use App\Models\RetentionDecision;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Kvkk\PersonalData\PersonalDataCollectorRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D2c imha motoru — üç aşama (atlanamaz):
 * 1) Tarama: yalnız aday listesi (veriye dokunmaz)
 * 2) Önizleme + insan kararı/onayı
 * 3) Uygulama: queue job, approval kaydı zorunlu
 *
 * İlke: Sistem hiçbir veriyi kendi başına imha etmez.
 */
class DestructionEngine
{
    public function __construct(
        private PersonalDataCollectorRegistry $collectors,
    ) {}

    public function hasActiveLegalHold(int $companyId, string $subjectType, int $subjectId): bool
    {
        return LegalHold::query()
            ->where('company_id', $companyId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('active', true)
            ->exists();
    }

    /**
     * AŞAMA 1 — Tarama. HİÇBİR VERİYE DOKUNMAZ.
     *
     * @return array{created: int, skipped_hold: int}
     */
    public function scan(int $companyId): array
    {
        $created = 0;
        $skippedHold = 0;

        $policies = RetentionPolicy::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->get();

        foreach ($policies as $policy) {
            $subjects = $this->findDueSubjects($policy);
            foreach ($subjects as $subject) {
                $subjectType = $subject['subject_type'];
                $subjectId = $subject['subject_id'];

                if ($this->hasActiveLegalHold($companyId, $subjectType, $subjectId)) {
                    $this->upsertCandidate($policy, $subjectType, $subjectId, $subject, DestructionCandidateStatus::SkippedLegalHold, 'Hukuki tutma nedeniyle atlandı');
                    $skippedHold++;

                    continue;
                }

                // Kalıcı kapsam dışı karar
                $excluded = RetentionDecision::query()
                    ->where('company_id', $companyId)
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->where('decision', RetentionDecisionType::Exclude->value)
                    ->exists();
                if ($excluded) {
                    continue;
                }

                // Aktif defer — defer_until geçmemişse aday pending/deferred olarak görünür
                $defer = RetentionDecision::query()
                    ->where('company_id', $companyId)
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->where('decision', RetentionDecisionType::Defer->value)
                    ->orderByDesc('id')
                    ->first();

                $status = DestructionCandidateStatus::Pending;
                $deferredUntil = null;
                if ($defer && $defer->defer_until && $defer->defer_until->isFuture()) {
                    $status = DestructionCandidateStatus::Deferred;
                    $deferredUntil = $defer->defer_until;
                }

                $this->upsertCandidate($policy, $subjectType, $subjectId, $subject, $status, null, $deferredUntil);
                $created++;
            }
        }

        return ['created' => $created, 'skipped_hold' => $skippedHold];
    }

    /**
     * @return list<array{subject_type: string, subject_id: int, due_since: \Carbon\CarbonInterface, record_count: int}>
     */
    private function findDueSubjects(RetentionPolicy $policy): array
    {
        $months = (int) $policy->retention_months;
        $cutoff = now()->subMonths($months);
        $out = [];

        if ($policy->trigger_event === RetentionTriggerEvent::BasvuruReddi) {
            $apps = JobApplication::query()
                ->where('company_id', $policy->company_id)
                ->where('status', 'rejected')
                ->where('updated_at', '<=', $cutoff)
                ->get(['id', 'updated_at']);
            foreach ($apps as $app) {
                $out[] = [
                    'subject_type' => 'candidate',
                    'subject_id' => (int) $app->id,
                    'due_since' => $app->updated_at,
                    'record_count' => 1,
                ];
            }
        }

        if ($policy->trigger_event === RetentionTriggerEvent::IstenAyrilma) {
            $emps = Employee::query()
                ->where('company_id', $policy->company_id)
                ->where('status', 'terminated')
                ->whereNotNull('termination_date')
                ->where('termination_date', '<=', $cutoff->toDateString())
                ->get(['id', 'termination_date']);
            foreach ($emps as $emp) {
                $out[] = [
                    'subject_type' => 'former_employee',
                    'subject_id' => (int) $emp->id,
                    'due_since' => \Carbon\Carbon::parse($emp->termination_date),
                    'record_count' => 1,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array{due_since?: mixed, record_count?: int}  $meta
     */
    private function upsertCandidate(
        RetentionPolicy $policy,
        string $subjectType,
        int $subjectId,
        array $meta,
        DestructionCandidateStatus $status,
        ?string $skipReason = null,
        mixed $deferredUntil = null,
    ): DestructionCandidate {
        $existing = DestructionCandidate::query()
            ->where('company_id', $policy->company_id)
            ->where('retention_policy_id', $policy->id)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereIn('status', [
                DestructionCandidateStatus::Pending->value,
                DestructionCandidateStatus::Deferred->value,
                DestructionCandidateStatus::SkippedLegalHold->value,
            ])
            ->first();

        $payload = [
            'company_id' => $policy->company_id,
            'retention_policy_id' => $policy->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'data_category' => $policy->data_category,
            'record_count' => (int) ($meta['record_count'] ?? 1),
            'due_since' => $meta['due_since'] ?? now(),
            'status' => $status,
            'strategy' => $policy->strategy,
            'skip_reason' => $skipReason,
            'deferred_until' => $deferredUntil,
        ];

        if ($existing) {
            // Tamamlanmış/onaylı adayları ezme
            if (in_array($existing->status, [
                DestructionCandidateStatus::Approved,
                DestructionCandidateStatus::Processing,
                DestructionCandidateStatus::Completed,
            ], true)) {
                return $existing;
            }
            $existing->forceFill($payload)->save();

            return $existing->fresh();
        }

        return DestructionCandidate::query()->create($payload);
    }

    /**
     * Karar: destroy | defer | exclude. Ertelemede gerekçe zorunlu.
     */
    public function decide(
        DestructionCandidate $candidate,
        User $actor,
        string $decision,
        string $reason,
        ?string $deferUntil = null,
    ): RetentionDecision {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Gerekçe zorunludur.']]);
        }

        $type = RetentionDecisionType::from($decision);

        if ($type === RetentionDecisionType::Defer && ! $deferUntil) {
            throw ValidationException::withMessages(['defer_until' => ['Erteleme tarihi zorunludur.']]);
        }

        $status = match ($type) {
            RetentionDecisionType::Destroy => DestructionCandidateStatus::Pending,
            RetentionDecisionType::Defer => DestructionCandidateStatus::Deferred,
            RetentionDecisionType::Exclude => DestructionCandidateStatus::Excluded,
        };

        $candidate->forceFill([
            'status' => $status,
            'deferred_until' => $type === RetentionDecisionType::Defer ? $deferUntil : null,
            'skip_reason' => $type === RetentionDecisionType::Exclude ? $reason : $candidate->skip_reason,
        ])->save();

        return RetentionDecision::query()->create([
            'company_id' => $candidate->company_id,
            'destruction_candidate_id' => $candidate->id,
            'subject_type' => $candidate->subject_type,
            'subject_id' => $candidate->subject_id,
            'decision' => $type,
            'reason' => $reason,
            'defer_until' => $deferUntil,
            'decided_by' => $actor->id,
            'created_at' => now(),
        ]);
    }

    /**
     * AŞAMA 2 — Dry-run. HİÇBİR DEĞİŞİKLİK YAPMAZ.
     *
     * @return array{preview: list<array<string, mixed>>, unchanged_proof: array<string, mixed>}
     */
    public function dryRun(DestructionCandidate $candidate): array
    {
        $strategy = ($candidate->strategy ?? RetentionStrategy::Anonymize)->toCollectorStrategy();

        $beforeHash = $this->subjectFingerprint($candidate->subject_type, (int) $candidate->subject_id, (int) $candidate->company_id);

        $preview = $this->collectors->destroyAll(
            $candidate->subject_type,
            (int) $candidate->subject_id,
            (int) $candidate->company_id,
            $strategy,
            true,
        );

        $afterHash = $this->subjectFingerprint($candidate->subject_type, (int) $candidate->subject_id, (int) $candidate->company_id);

        $snapshot = [
            'preview' => $preview,
            'unchanged_proof' => [
                'before' => $beforeHash,
                'after' => $afterHash,
                'identical' => $beforeHash === $afterHash,
            ],
        ];

        $candidate->forceFill(['preview_snapshot' => $snapshot])->save();

        return $snapshot;
    }

    /**
     * AŞAMA 2 — Onay. Job yalnız bu kayıt ile çalışır.
     *
     * @param  list<int>  $candidateIds
     */
    public function approve(int $companyId, User $actor, array $candidateIds, bool $dryRunConfirmed, ?string $note = null): DestructionApproval
    {
        if (! $dryRunConfirmed) {
            throw ValidationException::withMessages([
                'dry_run_confirmed' => ['Onaydan önce dry-run (Ne olacağını göster) zorunludur.'],
            ]);
        }

        if ($candidateIds === []) {
            throw ValidationException::withMessages(['candidate_ids' => ['En az bir aday seçilmelidir.']]);
        }

        $candidates = DestructionCandidate::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $candidateIds)
            ->get();

        if ($candidates->count() !== count(array_unique($candidateIds))) {
            throw ValidationException::withMessages(['candidate_ids' => ['Geçersiz aday seçimi.']]);
        }

        foreach ($candidates as $c) {
            if ($this->hasActiveLegalHold($companyId, $c->subject_type, (int) $c->subject_id)) {
                throw ValidationException::withMessages([
                    'candidate_ids' => ["Hukuki tutma nedeniyle imha onaylanamaz (#{$c->id})."],
                ]);
            }
            if ($c->status === DestructionCandidateStatus::Excluded) {
                throw ValidationException::withMessages([
                    'candidate_ids' => ["Kapsam dışı bırakılan aday onaylanamaz (#{$c->id})."],
                ]);
            }
        }

        $approval = DestructionApproval::query()->create([
            'company_id' => $companyId,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'candidate_ids' => array_values(array_map('intval', $candidateIds)),
            'dry_run_confirmed' => true,
            'status' => 'approved',
            'note' => $note,
        ]);

        DestructionCandidate::query()
            ->whereIn('id', $candidateIds)
            ->update([
                'status' => DestructionCandidateStatus::Approved->value,
                'approval_id' => $approval->id,
            ]);

        return $approval;
    }

    /**
     * AŞAMA 3 — Uygulama. Approval yoksa ASLA çalışmaz.
     */
    public function executeApproved(DestructionApproval $approval): void
    {
        if (! $approval->approved_at || ! $approval->approved_by) {
            throw new \RuntimeException('Onay kaydı olmadan imha çalıştırılamaz.');
        }

        $approval->forceFill(['status' => 'processing'])->save();

        $candidates = DestructionCandidate::query()
            ->where('approval_id', $approval->id)
            ->where('status', DestructionCandidateStatus::Approved->value)
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            $this->executeOne($candidate, $approval);
        }

        $failed = DestructionCandidate::query()
            ->where('approval_id', $approval->id)
            ->where('status', DestructionCandidateStatus::Failed->value)
            ->exists();

        $approval->forceFill([
            'status' => $failed ? 'failed' : 'completed',
        ])->save();
    }

    public function executeOne(DestructionCandidate $candidate, DestructionApproval $approval): void
    {
        if (! $approval->id || (int) $candidate->approval_id !== (int) $approval->id) {
            throw new \RuntimeException('Onay kaydı olmadan imha çalıştırılamaz.');
        }

        if ($this->hasActiveLegalHold((int) $candidate->company_id, $candidate->subject_type, (int) $candidate->subject_id)) {
            $candidate->forceFill([
                'status' => DestructionCandidateStatus::SkippedLegalHold,
                'skip_reason' => 'Hukuki tutma nedeniyle atlandı',
            ])->save();
            DestructionLog::query()->create([
                'company_id' => $candidate->company_id,
                'destruction_candidate_id' => $candidate->id,
                'approval_id' => $approval->id,
                'retention_policy_id' => $candidate->retention_policy_id,
                'subject_type' => $candidate->subject_type,
                'subject_id' => $candidate->subject_id,
                'data_category' => $candidate->data_category,
                'strategy' => $candidate->strategy?->value ?? 'anonymize',
                'rows_affected' => 0,
                'summary' => ['skipped' => 'legal_hold'],
                'approved_by' => $approval->approved_by,
                'dry_run' => false,
                'outcome' => 'skipped',
                'created_at' => now(),
            ]);

            return;
        }

        $candidate->forceFill(['status' => DestructionCandidateStatus::Processing])->save();
        $strategyEnum = $candidate->strategy ?? RetentionStrategy::Anonymize;
        $collectorStrategy = $strategyEnum->toCollectorStrategy();

        try {
            DB::transaction(function () use ($candidate, $approval, $collectorStrategy, $strategyEnum) {
                $results = $this->collectors->destroyAll(
                    $candidate->subject_type,
                    (int) $candidate->subject_id,
                    (int) $candidate->company_id,
                    $collectorStrategy,
                    false,
                );

                $rows = 0;
                foreach ($results as $r) {
                    $rows += (int) $r['result']['rows_affected'];
                    DestructionLog::query()->create([
                        'company_id' => $candidate->company_id,
                        'destruction_candidate_id' => $candidate->id,
                        'approval_id' => $approval->id,
                        'retention_policy_id' => $candidate->retention_policy_id,
                        'subject_type' => $candidate->subject_type,
                        'subject_id' => $candidate->subject_id,
                        'data_category' => $candidate->data_category,
                        'strategy' => $strategyEnum->value,
                        'collector_key' => $r['collector'],
                        'rows_affected' => $r['result']['rows_affected'],
                        'summary' => $r['result'],
                        'content_hash' => hash('sha256', json_encode($r['result'], JSON_UNESCAPED_UNICODE) ?: ''),
                        'approved_by' => $approval->approved_by,
                        'dry_run' => false,
                        'outcome' => 'success',
                        'created_at' => now(),
                    ]);
                }

                if ($results === []) {
                    DestructionLog::query()->create([
                        'company_id' => $candidate->company_id,
                        'destruction_candidate_id' => $candidate->id,
                        'approval_id' => $approval->id,
                        'retention_policy_id' => $candidate->retention_policy_id,
                        'subject_type' => $candidate->subject_type,
                        'subject_id' => $candidate->subject_id,
                        'data_category' => $candidate->data_category,
                        'strategy' => $strategyEnum->value,
                        'rows_affected' => 0,
                        'summary' => ['note' => 'no_collector_rows'],
                        'approved_by' => $approval->approved_by,
                        'dry_run' => false,
                        'outcome' => 'success',
                        'created_at' => now(),
                    ]);
                }

                $candidate->forceFill([
                    'status' => DestructionCandidateStatus::Completed,
                    'record_count' => $rows,
                ])->save();
            });
        } catch (\Throwable $e) {
            $candidate->forceFill(['status' => DestructionCandidateStatus::Failed])->save();
            DestructionLog::query()->create([
                'company_id' => $candidate->company_id,
                'destruction_candidate_id' => $candidate->id,
                'approval_id' => $approval->id,
                'retention_policy_id' => $candidate->retention_policy_id,
                'subject_type' => $candidate->subject_type,
                'subject_id' => $candidate->subject_id,
                'data_category' => $candidate->data_category,
                'strategy' => $strategyEnum->value,
                'rows_affected' => 0,
                'summary' => null,
                'approved_by' => $approval->approved_by,
                'dry_run' => false,
                'outcome' => 'failed',
                'error_message' => $e->getMessage(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Job doğrudan çağrılsa bile approval yoksa çalışmaz.
     */
    public function assertCanExecute(?DestructionApproval $approval): void
    {
        if (! $approval || ! $approval->approved_at || ! $approval->approved_by || ! $approval->dry_run_confirmed) {
            throw new \RuntimeException('Onay kaydı olmadan imha çalıştırılamaz.');
        }
    }

    /** @return array<string, mixed> */
    private function subjectFingerprint(string $subjectType, int $subjectId, int $companyId): array
    {
        if ($subjectType === 'candidate') {
            $app = JobApplication::query()->where('company_id', $companyId)->where('id', $subjectId)->first();
            if (! $app) {
                return [];
            }

            return [
                'id' => $app->id,
                'first_name' => $app->first_name,
                'last_name' => $app->last_name,
                'email' => $app->email,
                'phone' => $app->phone,
                'cv_path' => $app->cv_path,
                'status' => $app->status instanceof \BackedEnum ? $app->status->value : $app->status,
            ];
        }

        $emp = Employee::query()->where('company_id', $companyId)->where('id', $subjectId)->first();
        if (! $emp) {
            return [];
        }
        $user = $emp->user_id
            ? User::query()->where('id', $emp->user_id)->first()
            : null;

        return [
            'employee' => [
                'id' => $emp->id,
                'national_id' => $emp->national_id,
                'iban' => $emp->iban,
                'address' => $emp->address,
                'department_id' => $emp->department_id,
                'gender' => $emp->gender,
                'status' => $emp->status,
                'hire_date' => $emp->hire_date?->format('Y-m-d'),
                'termination_date' => $emp->termination_date?->format('Y-m-d'),
            ],
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ] : null,
        ];
    }
}
