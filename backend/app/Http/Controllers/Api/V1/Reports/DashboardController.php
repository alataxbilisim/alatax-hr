<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Dashboard;
use App\Services\Reports\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DashboardController extends BaseController
{
    public function __construct(
        protected DashboardService $dashboards,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->dashboards->listFor(
            $request->user(),
            (int) $this->getCompanyId(),
            $request->integer('per_page', 20),
            is_string($request->query('module_key')) ? $request->query('module_key') : null
        );

        return $this->paginated($page, 'Panolar');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'layout' => 'nullable|array',
            'global_filters' => 'nullable|array',
            'shares' => 'nullable|array',
        ]);

        try {
            $d = $this->dashboards->create($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success($d, 'Pano oluşturuldu', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $dashboard = $this->findAccessible($request, $id);

        return $this->success($dashboard->load('shares'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dashboard = $this->findAccessible($request, $id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'layout' => 'nullable|array',
            'global_filters' => 'nullable|array',
            'shares' => 'nullable|array',
        ]);

        try {
            $d = $this->dashboards->update($dashboard, $request->user(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success($d, 'Pano güncellendi');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $dashboard = $this->findAccessible($request, $id);
        try {
            $this->dashboards->delete($dashboard, $request->user());
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success(null, 'Pano silindi');
    }

    public function run(Request $request, int $id): JsonResponse
    {
        $dashboard = $this->findAccessible($request, $id);
        $validated = $request->validate([
            'values' => 'nullable|array',
            'cross' => 'nullable|array',
        ]);

        try {
            $result = $this->dashboards->runBatch(
                $dashboard,
                $request->user(),
                (int) $this->getCompanyId(),
                $validated
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Pano çalıştırıldı');
    }

    public function cloneDashboard(Request $request, int $id): JsonResponse
    {
        $dashboard = $this->findAccessible($request, $id);
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);
        $copy = $this->dashboards->cloneForCompany(
            $dashboard,
            $request->user(),
            (int) $this->getCompanyId(),
            $validated['name'] ?? null
        );

        return $this->success($copy, 'Pano kopyalandı', 201);
    }

    /**
     * Sistem panosu — system_key ile (analytics / varsayılan rol).
     */
    public function showBySystemKey(Request $request, string $systemKey): JsonResponse
    {
        $dashboard = Dashboard::withoutGlobalScope('company')
            ->whereNull('company_id')
            ->where('is_system', true)
            ->where('system_key', $systemKey)
            ->first();
        if (! $dashboard || ! $dashboard->isAccessibleBy($request->user())) {
            abort(404, 'Pano bulunamadı');
        }

        return $this->success($dashboard->load('shares'));
    }

    private function findAccessible(Request $request, int $id): Dashboard
    {
        $companyId = (int) $this->getCompanyId();
        $dashboard = Dashboard::withoutGlobalScope('company')
            ->whereKey($id)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('company_id')->where('is_system', true);
                    });
            })
            ->first();

        if (! $dashboard || ! $dashboard->isAccessibleBy($request->user())) {
            abort(404, 'Pano bulunamadı');
        }

        return $dashboard;
    }
}
