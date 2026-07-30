<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\Payslip;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class PayslipCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'payslip';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.payslip';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $employeeId = $this->resolveEmployeeId($subjectType, $subjectId, $companyId);
        if (! $employeeId || ! class_exists(Payslip::class)) {
            return [];
        }

        $rows = Payslip::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->get(['id', 'period', 'year', 'month', 'gross_salary', 'net_salary', 'file_path', 'is_published', 'published_at']);

        $records = $rows->map(fn ($p) => [
            'source' => 'payslips',
            'id' => $p->id,
            'period' => $p->period,
            'year' => $p->year,
            'month' => $p->month,
            'gross_salary' => $p->gross_salary,
            'net_salary' => $p->net_salary,
            'is_published' => $p->is_published,
            'published_at' => $p->published_at,
        ])->all();

        $files = $rows
            ->filter(fn ($p) => filled($p->file_path))
            ->map(fn ($p) => ['path' => (string) $p->file_path, 'name' => 'payslip_'.$p->period])
            ->values()
            ->all();

        return $records === [] ? [] : [[
            'category' => 'payroll',
            'label' => 'Bordrolar',
            'records' => $records,
            'files' => $files,
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // D2c: istatistik kay�tlar� kal�r; kimlik alanlar� �st collector (employee_profile) maskeler.
        // Bu collector i�in ek PII yoksa no-op; dry-run da ayn� sonucu d�ner.
        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
    }
}
