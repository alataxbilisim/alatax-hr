<?php

namespace App\Http\Controllers\Api\V1\Salary;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Employee;
use App\Services\DataScopeService;
use App\Services\EmployeeSensitiveFieldService;
use App\Services\LookupService;
use App\Services\Salary\SalaryRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeSalaryController extends BaseController
{
    public function __construct(
        protected SalaryRecordService $salaryRecords,
        protected EmployeeSensitiveFieldService $sensitive,
        protected DataScopeService $dataScope,
        protected LookupService $lookups,
    ) {}

    public function index(int $id): JsonResponse
    {
        $user = auth()->user();
        if (! $this->sensitive->canViewSalary($user)) {
            return $this->error('Ücret görüntüleme yetkiniz yok', 403);
        }

        $employee = $this->findScopedEmployee($id);
        if ($employee === null) {
            return $this->error('Personel bulunamadı', 404);
        }

        $summary = $this->salaryRecords->summaryForEmployee($employee);
        $band = app(\App\Services\Salary\SalaryBandService::class)
            ->indicatorForEmployee($employee, $summary['current']['amount'] ?? $employee->gross_salary);

        return $this->success([
            'current' => $summary['current'],
            'records' => $summary['records'],
            'band' => $band,
        ], 'Ücret geçmişi');
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (! $this->sensitive->canEditSalary($user)) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $employee = $this->findScopedEmployee($id, true);
        if ($employee === null) {
            return $this->error('Personel bulunamadı', 404);
        }

        $validated = $request->validate([
            'effective_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'change_reason' => 'required|string|max:64',
            'note' => 'nullable|string|max:2000',
        ]);

        if (isset($validated['currency'])) {
            $this->lookups->assertValid(
                LookupService::TYPE_CURRENCY,
                $validated['currency'],
                $this->getCompanyId(),
                'currency'
            );
        }

        $record = $this->salaryRecords->createForEmployee(
            $employee,
            $validated,
            auth()->id()
        );

        return $this->success($record, 'Ücret kaydı eklendi', 201);
    }

    private function findScopedEmployee(int $id, bool $forManage = false): ?Employee
    {
        $user = auth()->user();
        $employee = Employee::query()
            ->where('company_id', $this->getCompanyId())
            ->find($id);

        if ($employee === null) {
            return null;
        }

        if ($forManage) {
            if (! $this->dataScope->canManageHrRecords($user) || ! $this->dataScope->allowsEmployee($user, $employee)) {
                return null;
            }
        } elseif (! $this->dataScope->allowsEmployee($user, $employee)) {
            return null;
        }

        return $employee;
    }
}
