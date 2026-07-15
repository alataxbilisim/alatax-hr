<?php

namespace App\Services\Salary;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\SalaryReviewItem;
use App\Models\SalaryReviewPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zam dönemi atomik uygulama (ya hepsi ya hiçbiri).
 */
class SalaryReviewApplyService
{
    public function __construct(
        protected SalaryRecordService $salaryRecords,
    ) {}

    public function applyApprovedPeriod(SalaryReviewPeriod $period, ?int $approverId = null): void
    {
        if ($period->status === SalaryReviewPeriod::STATUS_APPROVED) {
            return;
        }

        DB::transaction(function () use ($period, $approverId): void {
            $period = SalaryReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status === SalaryReviewPeriod::STATUS_APPROVED) {
                return;
            }

            $items = SalaryReviewItem::query()
                ->where('period_id', $period->id)
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Uygulanacak öneri yok.'],
                ]);
            }

            foreach ($items as $item) {
                $employee = Employee::query()
                    ->where('company_id', $period->company_id)
                    ->where('id', $item->employee_id)
                    ->first();

                if ($employee === null) {
                    throw ValidationException::withMessages([
                        'items' => ["Personel bulunamadı (#{$item->employee_id})."],
                    ]);
                }

                $this->salaryRecords->createForEmployee($employee, [
                    'effective_date' => $period->effective_date->toDateString(),
                    'amount' => $item->proposed_amount,
                    'currency' => $item->currency,
                    'change_reason' => $item->change_reason,
                    'note' => 'Zam dönemi: '.$period->name.($item->note ? ' — '.$item->note : ''),
                ], $approverId);
            }

            $period->update([
                'status' => SalaryReviewPeriod::STATUS_APPROVED,
                'approved_by' => $approverId,
                'approved_at' => now(),
                'rejection_reason' => null,
                'updated_by' => $approverId,
            ]);

            ActivityLog::log(
                'update',
                $period,
                'Zam dönemi onaylandı — öneriler uygulandı',
                null,
                [
                    'period_id' => $period->id,
                    'items_count' => $items->count(),
                    'status' => SalaryReviewPeriod::STATUS_APPROVED,
                ]
            );
        });
    }
}
