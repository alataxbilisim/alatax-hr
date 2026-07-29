<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\SavedReport;
use App\Services\Reports\DatasetRegistry;
use App\Services\Reports\ReportDefinitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * D1a — Rapor motoru API (UI D1b'de).
 */
class ReportController extends BaseController
{
    public function __construct(
        protected DatasetRegistry $registry,
        protected ReportDefinitionService $reports,
    ) {}

    public function datasets(Request $request): JsonResponse
    {
        $companyId = (int) $this->getCompanyId();
        $catalog = $this->registry->catalog($request->user(), $companyId);

        return $this->success($catalog, 'Dataset kataloğu');
    }

    public function index(Request $request): JsonResponse
    {
        $page = $this->reports->listFor(
            $request->user(),
            (int) $this->getCompanyId(),
            $request->integer('per_page', 20)
        );

        return $this->paginated($page, 'Rapor tanımları');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'dataset_key' => 'required|string|max:64',
            'config' => 'nullable|array',
            'fields' => 'nullable|array',
            'filters' => 'nullable|array',
            'group_by' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'sorts' => 'nullable|array',
            'joins' => 'nullable|array',
            'is_shared' => 'sometimes|boolean',
            'share_user_ids' => 'nullable|array',
            'share_user_ids.*' => 'integer',
            'share_role_ids' => 'nullable|array',
            'share_role_ids.*' => 'integer',
            'is_favorite' => 'sometimes|boolean',
        ]);

        try {
            $report = $this->reports->create($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($report, 'Rapor kaydedildi', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);

        return $this->success($report);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'dataset_key' => 'sometimes|string|max:64',
            'config' => 'nullable|array',
            'fields' => 'nullable|array',
            'filters' => 'nullable|array',
            'group_by' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'sorts' => 'nullable|array',
            'joins' => 'nullable|array',
            'is_shared' => 'sometimes|boolean',
            'share_user_ids' => 'nullable|array',
            'share_user_ids.*' => 'integer',
            'share_role_ids' => 'nullable|array',
            'share_role_ids.*' => 'integer',
            'is_favorite' => 'sometimes|boolean',
        ]);

        try {
            $report = $this->reports->update($report, $request->user(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success($report, 'Rapor güncellendi');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);
        try {
            $this->reports->delete($report, $request->user());
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success(null, 'Rapor silindi');
    }

    public function run(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);
        $overrides = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:1000',
            'offset' => 'sometimes|integer|min:0',
        ]);

        try {
            $result = $this->reports->run($report, $request->user(), (int) $this->getCompanyId(), $overrides);
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Rapor çalıştırıldı');
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => 'required|string|max:64',
            'fields' => 'nullable|array',
            'fields.*' => 'string',
            'filters' => 'nullable|array',
            'group_by' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'sorts' => 'nullable|array',
            'joins' => 'nullable|array',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'offset' => 'sometimes|integer|min:0',
        ]);

        try {
            $result = $this->reports->preview($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Önizleme');
    }

    /**
     * Export satırları — query builder (FE sayfa verisi değil). Üst sınır 50k.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => 'required|string|max:64',
            'fields' => 'nullable|array',
            'fields.*' => 'string',
            'filters' => 'nullable|array',
            'group_by' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'sorts' => 'nullable|array',
            'joins' => 'nullable|array',
            'limit' => 'sometimes|integer|min:1|max:50000',
        ]);

        try {
            $result = $this->reports->export($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Export verisi');
    }

    public function exportSaved(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);

        try {
            $result = $this->reports->exportSaved($report, $request->user(), (int) $this->getCompanyId());
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Export verisi');
    }

    private function findAccessible(Request $request, int $id): SavedReport
    {
        $report = SavedReport::query()
            ->where('company_id', $this->getCompanyId())
            ->whereKey($id)
            ->first();

        if (! $report || ! $report->isAccessibleBy($request->user())) {
            abort(404, 'Rapor bulunamadı');
        }

        return $report;
    }
}
