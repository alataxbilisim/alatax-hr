<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\AttendanceRecord;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class AttendanceCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'attendance';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.attendance';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(AttendanceRecord::class)) {
            return [];
        }

        $records = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->get([
                'id', 'date', 'clock_in', 'clock_out', 'total_hours', 'overtime_hours',
                'late_minutes', 'status', 'source', 'branch_id', 'notes',
            ])
            ->map(fn ($r) => array_merge($r->toArray(), ['source' => 'attendance_records']))
            ->all();

        return $records === [] ? [] : [[
            'category' => 'attendance',
            'label' => 'Devam / puantaj kayıtları',
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
