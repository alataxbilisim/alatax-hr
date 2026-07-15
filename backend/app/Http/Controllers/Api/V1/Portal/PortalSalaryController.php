<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Employee;
use App\Services\Salary\SalaryRecordService;
use Illuminate\Http\JsonResponse;

/**
 * Portal — personel yalnız kendi ücretini görür.
 */
class PortalSalaryController extends BaseController
{
    public function __construct(
        protected SalaryRecordService $salaryRecords,
    ) {}

    public function me(): JsonResponse
    {
        $user = auth()->user();
        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->first();

        if ($employee === null) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $summary = $this->salaryRecords->summaryForEmployee($employee);

        return $this->success([
            'current' => $summary['current'],
            'records' => $summary['records']->map(fn ($r) => [
                'id' => $r->id,
                'effective_date' => $r->effective_date?->toDateString(),
                'amount' => $r->amount,
                'currency' => $r->currency,
                'change_reason' => $r->change_reason,
                'note' => $r->note,
            ])->values(),
        ], 'Ücretim');
    }

    /**
     * Başka personel id'si ile erişim denemesi — her zaman 403.
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $own = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->first();

        if ($own === null || (int) $own->id !== (int) $id) {
            return $this->error('Bu ücrete erişim yetkiniz yok', 403);
        }

        return $this->me();
    }
}
