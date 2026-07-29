<?php

namespace App\Services\Kvkk;

use App\Enums\KvkkConsentType;
use App\Models\ConsentRecord;
use App\Models\Employee;
use App\Models\PrivacyNotice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsentRecordService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function listFor(int $companyId, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return ConsentRecord::query()
            ->where('company_id', $companyId)
            ->with('notice:id,audience,version,title')
            ->when(isset($filters['subject_type']), fn ($q) => $q->where('subject_type', $filters['subject_type']))
            ->when(isset($filters['consent_type']), fn ($q) => $q->where('consent_type', $filters['consent_type']))
            ->when(isset($filters['granted']), fn ($q) => $q->where('granted', (bool) $filters['granted']))
            ->when(isset($filters['missing_only']) && $filters['missing_only'], function ($q) {
                // Aktif “aydınlatma_okundu” granted=true olmayan / withdrawn personel — ayrı endpoint
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * İK: aktif employee aydınlatması onaylamamış personel listesi.
     *
     * @return list<array<string, mixed>>
     */
    public function missingNoticeAcknowledgments(int $companyId): array
    {
        $notice = app(PrivacyNoticeService::class)->activeFor($companyId, 'employee');
        if ($notice === null) {
            return [];
        }

        $ackedUserIds = ConsentRecord::query()
            ->where('company_id', $companyId)
            ->where('subject_type', 'employee')
            ->where('notice_id', $notice->id)
            ->where('consent_type', KvkkConsentType::NoticeRead->value)
            ->where('granted', true)
            ->whereNull('withdrawn_at')
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missing = [];
        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->get(['id', 'user_id', 'employee_code']);

        foreach ($employees as $emp) {
            $userId = (int) $emp->user_id;
            if (in_array($userId, $ackedUserIds, true)) {
                continue;
            }
            $missing[] = [
                'user_id' => $userId,
                'employee_id' => (int) $emp->id,
                'employee_code' => $emp->employee_code,
                'notice_id' => $notice->id,
                'notice_version' => $notice->version,
            ];
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(int $companyId, array $data, Request $request): ConsentRecord
    {
        $type = KvkkConsentType::from((string) $data['consent_type']);
        $granted = (bool) $data['granted'];

        if (! empty($data['notice_id'])) {
            $notice = PrivacyNotice::query()
                ->where('company_id', $companyId)
                ->whereKey($data['notice_id'])
                ->firstOrFail();
            if (! $notice->isPublished()) {
                throw ValidationException::withMessages([
                    'notice_id' => ['Yalnız yayınlanmış aydınlatmaya rıza bağlanabilir.'],
                ]);
            }
        }

        return ConsentRecord::create([
            'company_id' => $companyId,
            'subject_type' => $data['subject_type'],
            'subject_id' => (int) $data['subject_id'],
            'notice_id' => $data['notice_id'] ?? null,
            'consent_type' => $type,
            'granted' => $granted,
            'granted_at' => $granted ? now() : null,
            'withdrawn_at' => null,
            'source' => $data['source'],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'evidence' => $data['evidence'] ?? null,
        ]);
    }

    /**
     * Geri çekme — kayıt silinmez.
     */
    public function withdraw(ConsentRecord $record): ConsentRecord
    {
        if ($record->withdrawn_at !== null) {
            throw ValidationException::withMessages([
                'consent' => ['Bu rıza zaten geri çekilmiş.'],
            ]);
        }
        if (! $record->granted) {
            throw ValidationException::withMessages([
                'consent' => ['Onaylanmamış kayıt geri çekilemez.'],
            ]);
        }

        $record->withdrawn_at = now();
        $record->granted = false;
        $record->save();

        return $record->fresh();
    }

    /**
     * Portal: personelin kendi rızasını geri çekmesi (subject = user_id).
     */
    public function withdrawOwn(int $companyId, int $userId, int $consentId): ConsentRecord
    {
        $record = ConsentRecord::query()
            ->where('company_id', $companyId)
            ->where('subject_type', 'employee')
            ->where('subject_id', $userId)
            ->whereKey($consentId)
            ->firstOrFail();

        return $this->withdraw($record);
    }
}
