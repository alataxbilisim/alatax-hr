<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\ConsentRecord;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class ConsentCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'consent';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.consent';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        if (! class_exists(ConsentRecord::class)) {
            return [];
        }

        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        $employeeId = $this->resolveEmployeeId($subjectType, $subjectId, $companyId);
        $ids = array_values(array_filter([$userId, $employeeId]));
        if ($ids === []) {
            return [];
        }

        $records = ConsentRecord::query()
            ->where('company_id', $companyId)
            ->whereIn('subject_id', $ids)
            ->get(['id', 'subject_type', 'subject_id', 'notice_id', 'consent_type', 'granted', 'granted_at', 'withdrawn_at', 'source'])
            ->map(fn ($c) => array_merge($c->toArray(), ['source' => 'consent_records']))
            ->all();

        return $records === [] ? [] : [[
            'category' => 'consent',
            'label' => 'Rıza kayıtları',
            'records' => $records,
            'files' => [],
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // D2c: istatistik kay�tlar� kal�r; kimlik alanlar� �st collector (employee_profile) maskeler.
        // Bu collector i�in ek PII yoksa no-op; dry-run da ayn� sonucu d�ner.
        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
    }
}
