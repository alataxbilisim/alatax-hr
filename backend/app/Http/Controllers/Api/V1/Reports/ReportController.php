<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\SavedReport;
use App\Services\Reports\DatasetRegistry;
use App\Services\Reports\ReportDefinitionService;
use App\Services\Reports\ReportPivotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * D1a/D1b/D1c — Rapor motoru API.
 */
class ReportController extends BaseController
{
    public function __construct(
        protected DatasetRegistry $registry,
        protected ReportDefinitionService $reports,
        protected ReportPivotService $pivotService,
    ) {}

    public function datasets(Request $request): JsonResponse
    {
        $companyId = (int) $this->getCompanyId();
        $catalog = $this->registry->catalog($request->user(), $companyId);

        return $this->success($catalog, 'Dataset kataloğu');
    }

    public function index(Request $request): JsonResponse
    {
        $moduleKey = $request->query('module_key');
        $page = $this->reports->listFor(
            $request->user(),
            (int) $this->getCompanyId(),
            $request->integer('per_page', 20),
            is_string($moduleKey) ? $moduleKey : null
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
            'folder_id' => 'nullable|integer',
            'shares' => 'nullable|array',
            'shares.*.user_id' => 'nullable|integer',
            'shares.*.role_id' => 'nullable|integer',
            'shares.*.department_id' => 'nullable|integer',
            'shares.*.level' => 'nullable|in:viewer,editor',
            'cache_ttl_seconds' => 'nullable|integer|min:0|max:86400',
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
            'folder_id' => 'nullable|integer',
            'shares' => 'nullable|array',
            'shares.*.user_id' => 'nullable|integer',
            'shares.*.role_id' => 'nullable|integer',
            'shares.*.department_id' => 'nullable|integer',
            'shares.*.level' => 'nullable|in:viewer,editor',
            'cache_ttl_seconds' => 'nullable|integer|min:0|max:86400',
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

    public function transfer(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $report = $this->reports->transfer($report, $request->user(), (int) $validated['user_id']);

        return $this->success($report, 'Sahiplik devredildi');
    }

    public function run(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);
        $overrides = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:1000',
            'offset' => 'sometimes|integer|min:0',
            'bypass_cache' => 'sometimes|boolean',
        ]);
        if (! empty($overrides['bypass_cache'])) {
            $overrides['__bypass_cache'] = true;
        }
        unset($overrides['bypass_cache']);

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
        $validated = $request->validate([
            'async' => 'sometimes|boolean',
        ]);

        // Ağır export → kuyruk + bildirim (30sn timeout riski)
        if (! empty($validated['async'])) {
            \App\Jobs\ProcessHeavyReportExportJob::dispatch(
                (int) $report->id,
                (int) $request->user()->id,
                (int) $this->getCompanyId(),
            );

            return $this->success(
                ['queued' => true, 'report_id' => $report->id],
                'Export kuyruğa alındı; hazır olunca bildirim gönderilecek',
                202
            );
        }

        try {
            $result = $this->reports->exportSaved($report, $request->user(), (int) $this->getCompanyId());
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Export verisi');
    }

    public function pivot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => 'required|string|max:64',
            'rows' => 'required|array|min:1|max:3',
            'columns' => 'nullable|array|max:2',
            'measures' => 'required|array|min:1|max:10',
            'filters' => 'nullable|array',
            'subtotals' => 'sometimes|boolean',
            'grand_total' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        try {
            $result = $this->pivotService->pivot($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Pivot');
    }

    public function drill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => 'required|string|max:64',
            'mode' => 'required|in:next,details',
            'filters' => 'nullable|array',
            'cell_filters' => 'nullable|array',
            'hierarchy_key' => 'nullable|string|max:64',
            'level' => 'sometimes|integer|min:0|max:10',
            'fields' => 'nullable|array',
            'measure' => 'nullable|array',
            'limit' => 'sometimes|integer|min:1|max:200',
            'offset' => 'sometimes|integer|min:0',
        ]);

        try {
            $result = $this->pivotService->drill($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Drill');
    }

    public function cloneReport(Request $request, int $id): JsonResponse
    {
        $report = $this->findAccessible($request, $id);
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);
        $copy = $this->reports->cloneForCompany(
            $report,
            $request->user(),
            (int) $this->getCompanyId(),
            $validated['name'] ?? null
        );

        return $this->success($copy, 'Rapor kopyalandı', 201);
    }

    private function findAccessible(Request $request, int $id): SavedReport
    {
        $companyId = (int) $this->getCompanyId();
        $report = SavedReport::withoutGlobalScope('company')
            ->whereKey($id)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('company_id')->where('is_system', true);
                    });
            })
            ->first();

        if (! $report || ! $report->isAccessibleBy($request->user())) {
            abort(404, 'Rapor bulunamadı');
        }

        return $report;
    }
}
