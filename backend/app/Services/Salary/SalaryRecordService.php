<?php

namespace App\Services\Salary;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\SalaryRecord;
use App\Services\EmployeeSensitiveFieldService;
use App\Services\LookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ücret geçmişi — employees.gross_salary senkron + maskeli audit.
 */
class SalaryRecordService
{
    public const REASON_INITIAL = 'initial';

    public function __construct(
        protected LookupService $lookups,
        protected EmployeeSensitiveFieldService $sensitive,
    ) {}

    /**
     * @param  array{
     *   effective_date: string,
     *   amount: float|int|string,
     *   currency?: string,
     *   change_reason: string,
     *   note?: string|null
     * }  $data
     */
    public function createForEmployee(Employee $employee, array $data, ?int $actorId = null): SalaryRecord
    {
        $this->lookups->assertValid(
            LookupService::TYPE_SALARY_CHANGE_REASON,
            $data['change_reason'],
            (int) $employee->company_id,
            'change_reason'
        );

        $amount = round((float) $data['amount'], 2);
        if ($amount < 0) {
            throw ValidationException::withMessages([
                'amount' => ['Ücret negatif olamaz.'],
            ]);
        }

        return DB::transaction(function () use ($employee, $data, $amount, $actorId) {
            $wasAuditing = SalaryRecord::$auditingEnabled;
            SalaryRecord::$auditingEnabled = false;

            try {
                $record = SalaryRecord::create([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'effective_date' => $data['effective_date'],
                    'amount' => $amount,
                    'currency' => $data['currency'] ?? ($employee->currency ?: 'TRY'),
                    'change_reason' => $data['change_reason'],
                    'note' => $data['note'] ?? null,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            } finally {
                SalaryRecord::$auditingEnabled = $wasAuditing;
            }

            // employees güncellemesi Auditable ile maskeli log üretir
            $this->syncEmployeeCache($employee, $record);

            ActivityLog::log(
                'create',
                $record,
                'Personel ücreti değişti',
                null,
                [
                    'employee_id' => $employee->id,
                    'effective_date' => $record->effective_date?->toDateString(),
                    'change_reason' => $record->change_reason,
                    'amount' => '*** güncellendi',
                ]
            );

            return $record->fresh(['creator:id,name']);
        });
    }

    public function syncEmployeeCache(Employee $employee, SalaryRecord $record): void
    {
        $employee->forceFill([
            'gross_salary' => $record->amount,
            'currency' => $record->currency,
        ])->save();
    }

    /**
     * Dolu gross_salary için tek seferlik başlangıç kaydı (idempotent).
     */
    public function ensureInitialFromEmployee(Employee $employee, ?int $actorId = null): ?SalaryRecord
    {
        if ($employee->gross_salary === null) {
            return null;
        }

        $exists = SalaryRecord::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->createForEmployee($employee, [
            'effective_date' => $employee->hire_date?->toDateString()
                ?? $employee->created_at?->toDateString()
                ?? now()->toDateString(),
            'amount' => $employee->gross_salary,
            'currency' => $employee->currency ?: 'TRY',
            'change_reason' => self::REASON_INITIAL,
            'note' => 'Mevcut maaş alanından otomatik başlangıç kaydı',
        ], $actorId);
    }

    public function backfillCompany(int $companyId): int
    {
        $count = 0;
        Employee::withoutCompanyScope()
            ->where('company_id', $companyId)
            ->whereNotNull('gross_salary')
            ->orderBy('id')
            ->each(function (Employee $employee) use (&$count): void {
                if ($this->ensureInitialFromEmployee($employee) !== null) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return array{current: array<string, mixed>|null, records: \Illuminate\Support\Collection}
     */
    public function summaryForEmployee(Employee $employee): array
    {
        $this->ensureInitialFromEmployee($employee);

        $records = SalaryRecord::query()
            ->where('employee_id', $employee->id)
            ->with(['creator:id,name'])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();

        $latest = $records->first();

        return [
            'current' => $latest ? [
                'amount' => $latest->amount,
                'currency' => $latest->currency,
                'effective_date' => $latest->effective_date?->toDateString(),
                'change_reason' => $latest->change_reason,
            ] : (
                $employee->gross_salary !== null
                    ? [
                        'amount' => $employee->gross_salary,
                        'currency' => $employee->currency ?: 'TRY',
                        'effective_date' => null,
                        'change_reason' => null,
                    ]
                    : null
            ),
            'records' => $records,
        ];
    }
}
