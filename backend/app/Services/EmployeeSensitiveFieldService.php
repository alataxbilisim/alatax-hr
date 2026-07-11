<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

/**
 * Employee hassas alan izinleri (Faz 2 alan seviyesi).
 * Okuma: Resource; yazma: strip + audit; log: maskeleme.
 */
class EmployeeSensitiveFieldService
{
    /** salary.view / salary.edit grubu (K6: bank + sgk dahil) */
    public const SALARY_FIELDS = [
        'gross_salary',
        'net_salary',
        'bank_name',
        'iban',
        'sgk_number',
        'sgk_start_date',
    ];

    public const TCKN_FIELDS = [
        'national_id',
    ];

    public const ALL_SENSITIVE_FIELDS = [
        'gross_salary',
        'net_salary',
        'bank_name',
        'iban',
        'sgk_number',
        'sgk_start_date',
        'national_id',
    ];

    public function canViewSalary(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('employees.salary.view');
    }

    public function canEditSalary(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('employees.salary.edit');
    }

    /**
     * TCKN: own (user_id eşleşmesi) VEYA employees.tckn.view
     */
    public function canViewTckn(?User $user, Employee $employee): bool
    {
        if (! $user) {
            return false;
        }

        if ($employee->user_id && (int) $employee->user_id === (int) $user->id) {
            return true;
        }

        return $user->can('employees.tckn.view');
    }

    /**
     * TCKN yazma: tckn.view (ayrı edit izni yok — İK görüyorsa güncelleyebilir)
     */
    public function canEditTckn(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('employees.tckn.view');
    }

    /**
     * Yetkisiz hassas alanları validated diziden çıkar (K2 — sessiz strip).
     *
     * @return array{data: array, stripped: list<string>}
     */
    public function stripUnauthorizedWrite(?User $user, array $validated): array
    {
        $stripped = [];

        if (! $this->canEditSalary($user)) {
            foreach (self::SALARY_FIELDS as $field) {
                if (array_key_exists($field, $validated)) {
                    unset($validated[$field]);
                    $stripped[] = $field;
                }
            }
        }

        if (! $this->canEditTckn($user)) {
            foreach (self::TCKN_FIELDS as $field) {
                if (array_key_exists($field, $validated)) {
                    unset($validated[$field]);
                    $stripped[] = $field;
                }
            }
        }

        return [
            'data' => $validated,
            'stripped' => $stripped,
        ];
    }

    /**
     * Audit old/new values: hassas alan değerlerini maskele (K7).
     */
    public function maskForAudit(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::ALL_SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $values) && $values[$field] !== null) {
                $values[$field] = '***';
            }
        }

        return $values;
    }

    /**
     * Değişen hassas alanlar için açıklama parçası (maskeli).
     *
     * @return list<string>
     */
    public function changedSensitiveLabels(array $oldValues, array $newValues): array
    {
        $labels = [
            'gross_salary' => 'maaş',
            'net_salary' => 'net maaş',
            'bank_name' => 'banka',
            'iban' => 'iban',
            'sgk_number' => 'sgk',
            'sgk_start_date' => 'sgk başlangıç',
            'national_id' => 'tckn',
        ];

        $notes = [];
        foreach ($labels as $field => $label) {
            $old = $oldValues[$field] ?? null;
            $new = $newValues[$field] ?? null;
            if ((string) $old !== (string) $new) {
                $notes[] = "{$label} güncellendi";
            }
        }

        return $notes;
    }

    public function isSalaryMeasure(string $measure): bool
    {
        return in_array($measure, [
            'salary',
            'gross_salary',
            'net_salary',
            'avg_gross_salary',
            'avg_net_salary',
        ], true);
    }
}
