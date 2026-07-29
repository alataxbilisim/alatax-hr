<?php

namespace App\Services\Reports;

use App\Models\Dashboard;
use App\Models\DashboardShare;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * D1d — Dashboard v2: CRUD + batch run (motor tüketici).
 */
class DashboardService
{
    public const MAX_WIDGETS = 20;

    public const WIDGET_TIMEOUT_SEC = 8;

    public const BATCH_TIMEOUT_SEC = 45;

    public const MIN_REFRESH_SEC = 30;

    public const WIDGET_TYPES = ['kpi', 'chart', 'table', 'pivot', 'text'];

    public function __construct(
        protected ReportDefinitionService $reports,
        protected ReportQueryBuilder $builder,
        protected ReportPivotService $pivot,
        protected DatasetRegistry $registry,
    ) {}

    public function listFor(User $user, int $companyId, int $perPage = 20, ?string $moduleKey = null): LengthAwarePaginator
    {
        return Dashboard::withoutGlobalScope('company')
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('company_id')->where('is_system', true);
                    });
            })
            ->when($moduleKey !== null && $moduleKey !== '', fn ($q) => $q->where('module_key', $moduleKey))
            ->accessibleBy($user)
            ->with('shares')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, int $companyId, array $data): Dashboard
    {
        $layout = $this->normalizeLayout($data['layout'] ?? ['widgets' => []]);

        $dashboard = Dashboard::create([
            'company_id' => $companyId,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'layout' => $layout,
            'global_filters' => $data['global_filters'] ?? ['fields' => []],
            'is_system' => false,
        ]);

        $this->syncShares($dashboard, $data['shares'] ?? []);

        return $dashboard->fresh(['shares']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Dashboard $dashboard, User $user, array $data): Dashboard
    {
        if (! $dashboard->canEdit($user)) {
            throw ValidationException::withMessages(['id' => ['Bu panoyu düzenleme yetkiniz yok']]);
        }
        if ($dashboard->is_system) {
            throw ValidationException::withMessages(['id' => ['Sistem panosu düzenlenemez']]);
        }

        if (isset($data['layout'])) {
            $dashboard->layout = $this->normalizeLayout($data['layout']);
        }
        foreach (['name', 'description'] as $k) {
            if (array_key_exists($k, $data)) {
                $dashboard->{$k} = $data[$k];
            }
        }
        if (array_key_exists('global_filters', $data)) {
            $dashboard->global_filters = $data['global_filters'];
        }
        $dashboard->save();

        if (array_key_exists('shares', $data)) {
            $this->syncShares($dashboard, $data['shares'] ?? []);
        }

        app(ReportResultCache::class)->forgetDashboard((int) $dashboard->id);

        return $dashboard->fresh(['shares']);
    }

    public function delete(Dashboard $dashboard, User $user): void
    {
        if ((int) $dashboard->owner_id !== (int) $user->id && ! $user->can('reports.dashboards.delete')) {
            throw ValidationException::withMessages(['id' => ['Bu panoyu silme yetkiniz yok']]);
        }
        if ($dashboard->is_system) {
            throw ValidationException::withMessages(['id' => ['Sistem panosu silinemez']]);
        }
        $dashboard->delete();
    }

    /**
     * Sistem panosunu firmaya kopyala.
     */
    public function cloneForCompany(Dashboard $source, User $user, int $companyId, ?string $name = null): Dashboard
    {
        if (! $source->isAccessibleBy($user)) {
            abort(403, 'Bu panoya erişim yok');
        }

        return Dashboard::create([
            'company_id' => $companyId,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'name' => $name ?? ($source->name.' (kopya)'),
            'description' => $source->description,
            'module_key' => $source->module_key,
            'system_key' => null,
            'layout' => $source->layout,
            'global_filters' => $source->global_filters,
            'is_system' => false,
            'cache_ttl_seconds' => $source->cache_ttl_seconds,
        ])->fresh(['shares']);
    }

    /**
     * Tek istekte tüm widget'lar — viewer kapsamı (sahip miras alınmaz).
     *
     * @param  array<string, mixed>  $runtimeFilters  global + cross
     * @return array{widgets: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function runBatch(Dashboard $dashboard, User $viewer, int $companyId, array $runtimeFilters = []): array
    {
        if (! $dashboard->isAccessibleBy($viewer)) {
            throw ValidationException::withMessages(['id' => ['Bu panoya erişim yok']]);
        }
        if (! ($dashboard->is_system && $dashboard->company_id === null)
            && (int) $dashboard->company_id !== $companyId) {
            throw ValidationException::withMessages(['id' => ['Pano bulunamadı']]);
        }

        $widgets = $dashboard->widgets();
        $batchStart = microtime(true);
        $results = [];
        $partial = false;
        $warnings = [];

        foreach ($widgets as $widget) {
            if ((microtime(true) - $batchStart) > self::BATCH_TIMEOUT_SEC) {
                $partial = true;
                $warnings[] = 'Toplam süre üst sınırı aşıldı; kalan widget\'lar atlandı';
                break;
            }

            $id = is_string($widget['id'] ?? null) ? $widget['id'] : Str::uuid()->toString();
            $type = (string) ($widget['type'] ?? '');

            try {
                $widgetStart = microtime(true);
                $payload = $this->runWidget($widget, $viewer, $companyId, $runtimeFilters);
                $elapsed = microtime(true) - $widgetStart;
                if ($elapsed > self::WIDGET_TIMEOUT_SEC) {
                    // soft: still return but flag
                    $payload['meta']['slow'] = true;
                }
                $results[] = [
                    'id' => $id,
                    'type' => $type,
                    'success' => true,
                    'data' => $payload['data'] ?? null,
                    'meta' => array_merge($payload['meta'] ?? [], [
                        'ran_at' => now()->toIso8601String(),
                        'elapsed_ms' => (int) round($elapsed * 1000),
                        'filter_applied' => $payload['filter_applied'] ?? true,
                        'filter_skipped_keys' => $payload['filter_skipped_keys'] ?? [],
                    ]),
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'id' => $id,
                    'type' => $type,
                    'success' => false,
                    'data' => null,
                    'meta' => [
                        'ran_at' => now()->toIso8601String(),
                    ],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'widgets' => $results,
            'meta' => [
                'partial' => $partial,
                'warnings' => $warnings,
                'widget_count' => count($widgets),
                'data_scope' => app(\App\Services\DataScopeService::class)->resolve($viewer)->value,
                'batch_elapsed_ms' => (int) round((microtime(true) - $batchStart) * 1000),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $widget
     * @param  array<string, mixed>  $runtimeFilters
     * @return array{data: mixed, meta: array<string, mixed>, filter_applied?: bool, filter_skipped_keys?: list<string>}
     */
    private function runWidget(array $widget, User $viewer, int $companyId, array $runtimeFilters): array
    {
        $type = (string) ($widget['type'] ?? '');
        if (! in_array($type, self::WIDGET_TYPES, true)) {
            throw new InvalidArgumentException('Geçersiz widget tipi: '.$type);
        }

        if ($type === 'text') {
            $content = (string) ($widget['content'] ?? $widget['config']['content'] ?? '');

            return [
                'data' => ['content' => $this->escapeText($content)],
                'meta' => [],
                'filter_applied' => false,
                'filter_skipped_keys' => array_keys($runtimeFilters['values'] ?? []),
            ];
        }

        $ignoreCross = (bool) ($widget['ignore_cross_filter'] ?? false);
        $merged = $this->mergeFiltersForWidget($widget, $runtimeFilters, $ignoreCross, $companyId);

        // Kaynak: report_id (varsayılan) veya inline config
        $reportId = $widget['report_id'] ?? null;
        if (is_int($reportId) || (is_string($reportId) && ctype_digit($reportId))) {
            $report = SavedReport::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $reportId)
                ->first();
            if (! $report || ! $report->isAccessibleBy($viewer)) {
                throw new InvalidArgumentException('Widget raporu bulunamadı veya erişim yok');
            }

            return $this->runFromReport($report, $viewer, $companyId, $type, $widget, $merged);
        }

        $inline = is_array($widget['config'] ?? null) ? $widget['config'] : [];
        if (empty($inline['dataset'])) {
            throw new InvalidArgumentException('Widget kaynak config eksik');
        }

        return $this->runFromConfig($inline, $viewer, $companyId, $type, $widget, $merged);
    }

    /**
     * @param  array<string, mixed>  $widget
     * @param  array{filters: list<array<string, mixed>>, skipped: list<string>, applied: bool}  $merged
     * @return array{data: mixed, meta: array<string, mixed>, filter_applied: bool, filter_skipped_keys: list<string>}
     */
    private function runFromReport(
        SavedReport $report,
        User $viewer,
        int $companyId,
        string $type,
        array $widget,
        array $merged,
    ): array {
        $config = is_array($report->config) ? $report->config : [];
        $config['dataset'] = $report->dataset_key;
        $config['filters'] = array_merge(
            is_array($config['filters'] ?? null) ? $config['filters'] : [],
            $merged['filters']
        );

        return $this->dispatchByType($type, $config, $viewer, $companyId, $widget, $merged);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $widget
     * @param  array{filters: list<array<string, mixed>>, skipped: list<string>, applied: bool}  $merged
     * @return array{data: mixed, meta: array<string, mixed>, filter_applied: bool, filter_skipped_keys: list<string>}
     */
    private function runFromConfig(
        array $config,
        User $viewer,
        int $companyId,
        string $type,
        array $widget,
        array $merged,
    ): array {
        $config['filters'] = array_merge(
            is_array($config['filters'] ?? null) ? $config['filters'] : [],
            $merged['filters']
        );

        return $this->dispatchByType($type, $config, $viewer, $companyId, $widget, $merged);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $widget
     * @param  array{filters: list<array<string, mixed>>, skipped: list<string>, applied: bool}  $merged
     * @return array{data: mixed, meta: array<string, mixed>, filter_applied: bool, filter_skipped_keys: list<string>}
     */
    private function dispatchByType(
        string $type,
        array $config,
        User $viewer,
        int $companyId,
        array $widget,
        array $merged,
    ): array {
        $limit = min(max((int) ($widget['row_limit'] ?? 50), 1), 100);

        if ($type === 'pivot') {
            $pivotConfig = $config['pivot'] ?? $config;
            $pivotConfig['dataset'] = $config['dataset'] ?? $pivotConfig['dataset'] ?? null;
            $pivotConfig['filters'] = $config['filters'] ?? [];
            $data = $this->pivot->pivot($viewer, $companyId, $pivotConfig);

            return [
                'data' => $data,
                'meta' => $data['meta'] ?? [],
                'filter_applied' => $merged['applied'],
                'filter_skipped_keys' => $merged['skipped'],
            ];
        }

        $config['limit'] = $type === 'kpi' ? 1 : $limit;
        if ($type === 'kpi') {
            // KPI: tek aggregation sonucu
            if (empty($config['aggregations']) && empty($config['group_by'])) {
                $measure = $widget['measure'] ?? $config['measure'] ?? null;
                if (is_array($measure)) {
                    $config['aggregations'] = [$measure];
                    $config['fields'] = [$measure['field'] ?? '*'];
                } else {
                    $config['aggregations'] = [['fn' => 'count', 'field' => '*', 'alias' => 'value']];
                    $config['fields'] = ['id'];
                }
                $config['group_by'] = [];
            }
        }

        // Aggregate sorgularda dataset defaultSort (dimension ORDER BY) PG GROUP BY hatası verir.
        // Motor davranışına dokunmadan tüketicide defaultSort'u bastırırız (geçersiz alan → atlanır).
        $isAggregate = (! empty($config['aggregations'])) || (! empty($config['group_by']));
        if ($isAggregate && (empty($config['sorts']) || ! is_array($config['sorts']))) {
            $config['sorts'] = [['field' => '__dashboard_nosort__', 'dir' => 'asc']];
        }

        $result = $this->builder->run($viewer, $companyId, $config);

        if ($type === 'kpi') {
            $row = $result['rows'][0] ?? [];
            $alias = null;
            if (is_array($config['aggregations'][0] ?? null)) {
                $alias = $config['aggregations'][0]['alias'] ?? null;
            }
            $value = null;
            if (is_string($alias) && array_key_exists($alias, $row)) {
                $value = $row[$alias];
            } elseif ($row !== []) {
                $value = reset($row);
            }

            return [
                'data' => [
                    'value' => $value,
                    'comparison' => null,
                    'direction' => null,
                ],
                'meta' => $result['meta'],
                'filter_applied' => $merged['applied'],
                'filter_skipped_keys' => $merged['skipped'],
            ];
        }

        return [
            'data' => $result,
            'meta' => $result['meta'],
            'filter_applied' => $merged['applied'],
            'filter_skipped_keys' => $merged['skipped'],
        ];
    }

    /**
     * Global + cross filtreleri widget dataset alanlarına uygula.
     *
     * @param  array<string, mixed>  $widget
     * @param  array<string, mixed>  $runtimeFilters
     * @return array{filters: list<array<string, mixed>>, skipped: list<string>, applied: bool}
     */
    private function mergeFiltersForWidget(array $widget, array $runtimeFilters, bool $ignoreCross, int $companyId): array
    {
        $values = is_array($runtimeFilters['values'] ?? null) ? $runtimeFilters['values'] : [];
        $cross = (! $ignoreCross && is_array($runtimeFilters['cross'] ?? null))
            ? $runtimeFilters['cross']
            : [];

        $datasetKey = $this->resolveDatasetKey($widget);
        $fieldKeys = [];
        if ($datasetKey && $this->registry->has($datasetKey)) {
            foreach ($this->registry->get($datasetKey)->fieldsForCompany($companyId) as $f) {
                $fieldKeys[$f->key] = true;
            }
        }

        $filters = [];
        $skipped = [];
        $applied = false;

        $all = array_merge($values, $cross);
        foreach ($all as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if ($fieldKeys === [] || ! isset($fieldKeys[$key])) {
                $skipped[] = $key;

                continue;
            }
            if ($value === null || $value === '') {
                $filters[] = ['field' => $key, 'op' => 'is_null'];
            } elseif (is_array($value) && isset($value['from'], $value['to'])) {
                $filters[] = ['field' => $key, 'op' => 'date_range', 'value' => [$value['from'], $value['to']]];
            } elseif (is_array($value)) {
                $filters[] = ['field' => $key, 'op' => 'in', 'value' => array_values($value)];
            } else {
                $filters[] = ['field' => $key, 'op' => 'eq', 'value' => $value];
            }
            $applied = true;
        }

        return ['filters' => $filters, 'skipped' => $skipped, 'applied' => $applied];
    }

    /**
     * @param  array<string, mixed>  $widget
     */
    private function resolveDatasetKey(array $widget): ?string
    {
        if (isset($widget['config']['dataset']) && is_string($widget['config']['dataset'])) {
            return $widget['config']['dataset'];
        }
        $reportId = $widget['report_id'] ?? null;
        if ($reportId) {
            $r = SavedReport::query()->whereKey((int) $reportId)->first();

            return $r?->dataset_key;
        }

        return null;
    }

    /**
     * @return array{widgets: list<array<string, mixed>>}
     */
    private function normalizeLayout(mixed $layout): array
    {
        if (! is_array($layout)) {
            return ['widgets' => []];
        }
        $widgets = $layout['widgets'] ?? [];
        if (! is_array($widgets)) {
            throw ValidationException::withMessages(['layout' => ['widgets dizi olmalıdır']]);
        }
        if (count($widgets) > self::MAX_WIDGETS) {
            throw ValidationException::withMessages([
                'layout' => ['En fazla '.self::MAX_WIDGETS.' widget eklenebilir'],
            ]);
        }
        $normalized = [];
        foreach ($widgets as $w) {
            if (! is_array($w)) {
                continue;
            }
            $type = (string) ($w['type'] ?? '');
            if (! in_array($type, self::WIDGET_TYPES, true)) {
                throw ValidationException::withMessages(['layout' => ['Geçersiz widget tipi: '.$type]]);
            }
            $id = is_string($w['id'] ?? null) ? $w['id'] : (string) Str::uuid();
            $refresh = (int) ($w['refresh_interval'] ?? 0);
            if ($refresh > 0 && $refresh < self::MIN_REFRESH_SEC) {
                $refresh = self::MIN_REFRESH_SEC;
            }
            $normalized[] = [
                'id' => $id,
                'type' => $type,
                'title' => (string) ($w['title'] ?? ''),
                'report_id' => $w['report_id'] ?? null,
                'config' => is_array($w['config'] ?? null) ? $w['config'] : null,
                'visual' => is_array($w['visual'] ?? null) ? $w['visual'] : null,
                'layout' => is_array($w['layout'] ?? null) ? $w['layout'] : ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
                'refresh_interval' => $refresh,
                'ignore_cross_filter' => (bool) ($w['ignore_cross_filter'] ?? false),
                'row_limit' => min(max((int) ($w['row_limit'] ?? 50), 1), 100),
                'content' => $type === 'text' ? $this->escapeText((string) ($w['content'] ?? '')) : null,
                'measure' => is_array($w['measure'] ?? null) ? $w['measure'] : null,
            ];
        }

        return ['widgets' => $normalized];
    }

    private function syncShares(Dashboard $dashboard, mixed $shares): void
    {
        DashboardShare::query()->where('dashboard_id', $dashboard->id)->delete();
        if (! is_array($shares)) {
            return;
        }
        foreach ($shares as $s) {
            if (! is_array($s)) {
                continue;
            }
            $level = ($s['level'] ?? 'viewer') === 'editor' ? 'editor' : 'viewer';
            $userId = isset($s['user_id']) ? (int) $s['user_id'] : null;
            $roleId = isset($s['role_id']) ? (int) $s['role_id'] : null;
            $deptId = isset($s['department_id']) ? (int) $s['department_id'] : null;
            $targets = (int) (bool) $userId + (int) (bool) $roleId + (int) (bool) $deptId;
            if ($targets !== 1) {
                continue;
            }
            DashboardShare::create([
                'dashboard_id' => $dashboard->id,
                'company_id' => $dashboard->company_id,
                'user_id' => $userId,
                'role_id' => $roleId,
                'department_id' => $deptId,
                'level' => $level,
            ]);
        }
    }

    private function escapeText(string $content): string
    {
        // markdown-lite: XSS escape; sadece satır sonu korunur
        return htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
