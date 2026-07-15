<?php

namespace App\Services\Salary;

use App\Models\Employee;
use App\Models\SalaryReviewItem;
use App\Models\SalaryReviewPeriod;
use App\Services\LookupService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryReviewService
{
    public function __construct(
        protected LookupService $lookups,
        protected WorkflowService $workflows,
        protected SalaryBandService $bands,
    ) {}

    /**
     * @param  array{
     *   name: string,
     *   scope_type?: string,
     *   scope_id?: int|null,
     *   effective_date: string,
     *   notes?: string|null
     * }  $data
     */
    public function createPeriod(int $companyId, array $data, ?int $actorId = null): SalaryReviewPeriod
    {
        $scopeType = $data['scope_type'] ?? SalaryReviewPeriod::SCOPE_COMPANY;
        if (! in_array($scopeType, [
            SalaryReviewPeriod::SCOPE_COMPANY,
            SalaryReviewPeriod::SCOPE_DEPARTMENT,
            SalaryReviewPeriod::SCOPE_BRANCH,
        ], true)) {
            throw ValidationException::withMessages([
                'scope_type' => ['Geçersiz kapsam tipi.'],
            ]);
        }

        return DB::transaction(function () use ($companyId, $data, $scopeType, $actorId) {
            $period = SalaryReviewPeriod::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'scope_type' => $scopeType,
                'scope_id' => $scopeType === SalaryReviewPeriod::SCOPE_COMPANY
                    ? null
                    : ($data['scope_id'] ?? null),
                'effective_date' => $data['effective_date'],
                'status' => SalaryReviewPeriod::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->populateItems($period);

            return $period->fresh(['items.employee.user']);
        });
    }

    public function populateItems(SalaryReviewPeriod $period): void
    {
        $query = Employee::query()
            ->where('company_id', $period->company_id)
            ->where('status', 'active');

        if ($period->scope_type === SalaryReviewPeriod::SCOPE_DEPARTMENT && $period->scope_id) {
            $query->where('department_id', $period->scope_id);
        } elseif ($period->scope_type === SalaryReviewPeriod::SCOPE_BRANCH && $period->scope_id) {
            $query->where('branch_id', $period->scope_id);
        }

        $employees = $query->orderBy('id')->get();

        foreach ($employees as $employee) {
            SalaryReviewItem::updateOrCreate(
                [
                    'period_id' => $period->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'company_id' => $period->company_id,
                    'current_amount' => $employee->gross_salary,
                    'proposed_amount' => $employee->gross_salary ?? 0,
                    'increase_percent' => 0,
                    'currency' => $employee->currency ?: 'TRY',
                    'change_reason' => 'annual_raise',
                ]
            );
        }
    }

    /**
     * @param  array{proposed_amount?: float|int|string, increase_percent?: float|int|string|null, change_reason?: string, note?: string|null}  $data
     */
    public function updateItem(SalaryReviewPeriod $period, SalaryReviewItem $item, array $data): SalaryReviewItem
    {
        if ($period->status !== SalaryReviewPeriod::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'period' => ['Yalnızca taslak dönemde öneri güncellenebilir.'],
            ]);
        }

        if ((int) $item->period_id !== (int) $period->id) {
            throw ValidationException::withMessages([
                'item' => ['Satır bu döneme ait değil.'],
            ]);
        }

        $current = (float) ($item->current_amount ?? 0);
        $proposed = array_key_exists('proposed_amount', $data)
            ? round((float) $data['proposed_amount'], 2)
            : (float) $item->proposed_amount;

        if (array_key_exists('increase_percent', $data) && $data['increase_percent'] !== null
            && ! array_key_exists('proposed_amount', $data)) {
            $pct = (float) $data['increase_percent'];
            $proposed = round($current * (1 + ($pct / 100)), 2);
            $item->increase_percent = $pct;
        } elseif ($current > 0) {
            $item->increase_percent = round((($proposed - $current) / $current) * 100, 2);
        } else {
            $item->increase_percent = $data['increase_percent'] ?? null;
        }

        if (isset($data['change_reason'])) {
            $this->lookups->assertValid(
                LookupService::TYPE_SALARY_CHANGE_REASON,
                $data['change_reason'],
                (int) $period->company_id,
                'change_reason'
            );
            $item->change_reason = $data['change_reason'];
        }

        $item->proposed_amount = $proposed;
        if (array_key_exists('note', $data)) {
            $item->note = $data['note'];
        }
        $item->save();

        return $item->fresh(['employee.user']);
    }

    public function submitForApproval(SalaryReviewPeriod $period, int $actorId): SalaryReviewPeriod
    {
        if ($period->status !== SalaryReviewPeriod::STATUS_DRAFT
            && $period->status !== SalaryReviewPeriod::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'period' => ['Dönem onaya gönderilemez.'],
            ]);
        }

        if ($period->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Kapsamda personel yok.'],
            ]);
        }

        $period->update([
            'status' => SalaryReviewPeriod::STATUS_PENDING,
            'submitted_by' => $actorId,
            'submitted_at' => now(),
            'updated_by' => $actorId,
            'rejection_reason' => null,
        ]);

        $record = $this->workflows->startWorkflow($period->fresh(), [
            'period_name' => $period->name,
            'items_count' => $period->items()->count(),
        ]);

        if ($record === null) {
            $period->update([
                'status' => SalaryReviewPeriod::STATUS_DRAFT,
                'submitted_by' => null,
                'submitted_at' => null,
            ]);
            throw ValidationException::withMessages([
                'workflow' => ['salary_review onay akışı tanımlı değil.'],
            ]);
        }

        return $period->fresh(['items.employee.user']);
    }

    /**
     * @return array<string, mixed>
     */
    public function itemWithBand(SalaryReviewItem $item): array
    {
        $employee = $item->employee;
        $band = $employee
            ? $this->bands->indicatorForEmployee($employee, $item->proposed_amount)
            : ['band' => null, 'status' => null, 'ratio' => null, 'position' => null];

        return [
            'id' => $item->id,
            'employee_id' => $item->employee_id,
            'employee_name' => $employee?->user?->name,
            'employee_code' => $employee?->employee_code,
            'position' => $employee?->position,
            'current_amount' => $item->current_amount,
            'proposed_amount' => $item->proposed_amount,
            'increase_percent' => $item->increase_percent,
            'currency' => $item->currency,
            'change_reason' => $item->change_reason,
            'note' => $item->note,
            'band' => $band,
        ];
    }
}
