<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Middleware\ResolveBranchContext;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Şube karşılaştırma raporu — reports.cross_branch gerekir.
 * branch_manager bu uca erişemez (403).
 */
class BranchComparisonReportController extends BaseController
{
    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorized();
        }

        if (! $user->can('reports.cross_branch')) {
            return $this->forbidden('Şubeler arası rapor için yetkiniz yok.');
        }

        $companyId = $this->getCompanyId();
        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $rows = [];
        foreach ($branches as $branch) {
            // Karşılaştırma seçili şube global scope'unu atlar; DataScope tavanı korunur
            $query = Employee::withoutGlobalScope(ResolveBranchContext::SCOPE_NAME)
                ->where('company_id', $companyId)
                ->where('branch_id', $branch->id)
                ->where('status', 'active');
            $this->dataScope->scopeForEmployee($query, $user);

            $rows[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'active_employees' => (clone $query)->count(),
            ];
        }

        // Şubesiz personel
        $unassigned = Employee::withoutGlobalScope(ResolveBranchContext::SCOPE_NAME)
            ->where('company_id', $companyId)
            ->whereNull('branch_id')
            ->where('status', 'active');
        $this->dataScope->scopeForEmployee($unassigned, $user);

        return $this->success([
            'by_branch' => $rows,
            'unassigned_active' => $unassigned->count(),
        ]);
    }
}
