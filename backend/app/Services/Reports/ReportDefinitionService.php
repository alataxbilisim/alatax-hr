<?php

namespace App\Services\Reports;

use App\Models\ReportShare;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Rapor tanımı CRUD + çalıştırma (viewer DataScope ile).
 */
class ReportDefinitionService
{
    public function __construct(
        protected DatasetRegistry $registry,
        protected ReportQueryBuilder $builder,
        protected ReportResultCache $resultCache,
    ) {}

    public function listFor(User $user, int $companyId, int $perPage = 20, ?string $moduleKey = null): LengthAwarePaginator
    {
        return SavedReport::withoutGlobalScope('company')
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('company_id')->where('is_system', true);
                    });
            })
            ->whereNotNull('dataset_key')
            ->when($moduleKey !== null && $moduleKey !== '', fn ($q) => $q->where('module_key', $moduleKey))
            ->accessibleBy($user)
            ->with('shares')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, int $companyId, array $data): SavedReport
    {
        $this->assertDataset($data['dataset_key'] ?? null);
        $config = $this->normalizeConfig($data);

        $report = SavedReport::create([
            'company_id' => $companyId,
            'folder_id' => $data['folder_id'] ?? null,
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'dataset_key' => $data['dataset_key'],
            'config' => $config,
            'is_shared' => (bool) ($data['is_shared'] ?? false),
            'share_user_ids' => $data['share_user_ids'] ?? null,
            'share_role_ids' => $data['share_role_ids'] ?? null,
            'is_system' => false,
            'is_favorite' => (bool) ($data['is_favorite'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'cache_ttl_seconds' => array_key_exists('cache_ttl_seconds', $data)
                ? ($data['cache_ttl_seconds'] === null ? null : max(0, (int) $data['cache_ttl_seconds']))
                : null,
        ]);

        if (array_key_exists('shares', $data)) {
            $this->syncShares($report, $data['shares']);
        }

        return $report->fresh(['shares']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SavedReport $report, User $user, array $data): SavedReport
    {
        if (! $report->canEdit($user)) {
            abort(403, 'Bu raporu düzenleme yetkiniz yok');
        }
        if ($report->is_system) {
            throw ValidationException::withMessages(['id' => ['Sistem raporu düzenlenemez']]);
        }

        if (array_key_exists('dataset_key', $data)) {
            $this->assertDataset($data['dataset_key']);
            $report->dataset_key = $data['dataset_key'];
        }
        if (array_key_exists('name', $data)) {
            $report->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $report->description = $data['description'];
        }
        if (array_key_exists('folder_id', $data)) {
            $report->folder_id = $data['folder_id'];
        }
        if (array_key_exists('config', $data) || array_key_exists('fields', $data)) {
            $merged = array_merge(['dataset_key' => $report->dataset_key], $data);
            $report->config = $this->normalizeConfig($merged);
        }
        foreach (['is_shared', 'is_favorite'] as $boolKey) {
            if (array_key_exists($boolKey, $data)) {
                $report->{$boolKey} = (bool) $data[$boolKey];
            }
        }
        foreach (['share_user_ids', 'share_role_ids'] as $jsonKey) {
            if (array_key_exists($jsonKey, $data)) {
                $report->{$jsonKey} = $data[$jsonKey];
            }
        }
        if (array_key_exists('cache_ttl_seconds', $data)) {
            $report->cache_ttl_seconds = $data['cache_ttl_seconds'] === null
                ? null
                : max(0, (int) $data['cache_ttl_seconds']);
        }
        $report->save();

        if (array_key_exists('shares', $data)) {
            if (! $report->canManageShares($user)) {
                abort(403, 'Paylaşım yalnız sahip tarafından yönetilir');
            }
            $this->syncShares($report, $data['shares']);
        }

        $this->resultCache->forgetReport((int) $report->id);

        return $report->fresh(['shares']);
    }

    public function delete(SavedReport $report, User $user): void
    {
        if ($report->is_system) {
            throw ValidationException::withMessages(['id' => ['Sistem raporu silinemez']]);
        }
        if (! $report->canDelete($user)) {
            abort(403, 'Bu raporu silme yetkiniz yok');
        }
        $this->resultCache->forgetReport((int) $report->id);
        $report->delete();
    }

    /**
     * Sahiplik devri (offboarding'e bağlanmaz — DUR).
     */
    public function transfer(SavedReport $report, User $actor, int $newOwnerId): SavedReport
    {
        if (! $report->canTransfer($actor)) {
            abort(403, 'Sahiplik devri yetkiniz yok');
        }
        $newOwner = User::query()
            ->where('company_id', $report->company_id)
            ->whereKey($newOwnerId)
            ->first();
        if (! $newOwner) {
            throw ValidationException::withMessages(['user_id' => ['Yeni sahip bulunamadı']]);
        }
        $report->user_id = $newOwner->id;
        $report->save();

        return $report->fresh(['shares']);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function run(SavedReport $report, User $viewer, int $companyId, array $overrides = []): array
    {
        if (! $report->isAccessibleBy($viewer)) {
            throw ValidationException::withMessages(['id' => ['Bu rapora erişim yok']]);
        }
        if (! $this->belongsToCompanyOrSystem($report, $companyId)) {
            throw ValidationException::withMessages(['id' => ['Rapor bulunamadı']]);
        }

        $config = is_array($report->config) ? $report->config : [];
        $config['dataset'] = $report->dataset_key;
        $config = array_merge($config, $overrides);
        $config['dataset'] = $report->dataset_key;

        $bypass = (bool) ($overrides['__bypass_cache'] ?? false);
        $ttl = $this->resultCache->resolveTtl($report->cache_ttl_seconds);
        $cacheKey = $this->resultCache->key(
            $viewer,
            $companyId,
            array_merge($config, ['_ver' => $this->resultCache->reportVersion((int) $report->id)]),
            (int) $report->id,
        );

        if (! $bypass && $ttl > 0) {
            $cached = $this->resultCache->get($cacheKey);
            if ($cached !== null) {
                $cached['meta']['cache_hit'] = true;
                $cached['meta']['computed_at'] = $cached['meta']['computed_at'] ?? now()->toIso8601String();

                return $cached;
            }
        }

        $started = microtime(true);
        $result = $this->runWithGuards($viewer, $companyId, $config);
        $duration = microtime(true) - $started;
        $this->resultCache->logSlowQuery(
            (string) $report->dataset_key,
            $duration,
            (int) ($result['meta']['count'] ?? count($result['rows']))
        );
        $result['meta']['cache_hit'] = false;
        $result['meta']['computed_at'] = now()->toIso8601String();
        $result['meta']['cache_ttl_seconds'] = $ttl;

        if (! $bypass && $ttl > 0) {
            $this->resultCache->put($cacheKey, $result, $ttl);
        }

        if (! isset($overrides['__schedule_id'])) {
            $this->logAccess($report, $viewer, $companyId, 'run', $result, $config, $started);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function preview(User $user, int $companyId, array $config): array
    {
        if (empty($config['dataset'])) {
            throw new InvalidArgumentException('dataset zorunlu');
        }
        $this->assertDataset($config['dataset']);

        $started = microtime(true);
        $result = $this->runWithGuards($user, $companyId, $config);
        ReportAccessLogger::record([
            'company_id' => $companyId,
            'user_id' => (int) $user->id,
            'action' => 'preview',
            'dataset_key' => (string) $config['dataset'],
            'row_count' => (int) ($result['meta']['count'] ?? count($result['rows'])),
            'contains_sensitive' => $this->resultHasSensitive($result),
            'sensitive_fields' => $this->resultSensitiveKeys($result),
            'filters' => is_array($config['filters'] ?? null) ? $config['filters'] : [],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function export(User $user, int $companyId, array $config): array
    {
        if (empty($config['dataset'])) {
            throw new InvalidArgumentException('dataset zorunlu');
        }
        $this->assertDataset($config['dataset']);
        $config['__export'] = true;
        $config['offset'] = 0;
        if (! isset($config['limit'])) {
            $config['limit'] = ReportQueryBuilder::EXPORT_MAX_ROWS;
        }

        $started = microtime(true);
        $result = $this->runWithGuards($user, $companyId, $config);
        ReportAccessLogger::record([
            'company_id' => $companyId,
            'user_id' => (int) $user->id,
            'action' => 'export',
            'dataset_key' => (string) $config['dataset'],
            'row_count' => (int) ($result['meta']['count'] ?? count($result['rows'])),
            'contains_sensitive' => $this->resultHasSensitive($result),
            'sensitive_fields' => $this->resultSensitiveKeys($result),
            'filters' => is_array($config['filters'] ?? null) ? $config['filters'] : [],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        return $result;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function exportSaved(SavedReport $report, User $viewer, int $companyId): array
    {
        if (! $report->isAccessibleBy($viewer)) {
            throw ValidationException::withMessages(['id' => ['Bu rapora erişim yok']]);
        }
        if (! $this->belongsToCompanyOrSystem($report, $companyId)) {
            throw ValidationException::withMessages(['id' => ['Rapor bulunamadı']]);
        }

        $config = is_array($report->config) ? $report->config : [];
        $config['dataset'] = $report->dataset_key;
        $config['__export'] = true;
        $config['offset'] = 0;
        $config['limit'] = ReportQueryBuilder::EXPORT_MAX_ROWS;

        $started = microtime(true);
        $result = $this->runWithGuards($viewer, $companyId, $config);
        $this->logAccess($report, $viewer, $companyId, 'export', $result, $config, $started);

        return $result;
    }

    public function syncShares(SavedReport $report, mixed $shares): void
    {
        ReportShare::query()->where('saved_report_id', $report->id)->delete();
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
            ReportShare::create([
                'saved_report_id' => $report->id,
                'company_id' => $report->company_id,
                'user_id' => $userId,
                'role_id' => $roleId,
                'department_id' => $deptId,
                'level' => $level,
            ]);
        }
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, meta: array<string, mixed>}  $result
     * @param  array<string, mixed>  $config
     */
    private function logAccess(
        SavedReport $report,
        User $viewer,
        int $companyId,
        string $action,
        array $result,
        array $config,
        float $started,
    ): void {
        ReportAccessLogger::record([
            'company_id' => $companyId,
            'user_id' => (int) $viewer->id,
            'report_id' => (int) $report->id,
            'action' => $action,
            'dataset_key' => $report->dataset_key,
            'row_count' => (int) ($result['meta']['count'] ?? count($result['rows'])),
            'contains_sensitive' => $this->resultHasSensitive($result),
            'sensitive_fields' => $this->resultSensitiveKeys($result),
            'filters' => is_array($config['filters'] ?? null) ? $config['filters'] : [],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * @param  array{meta?: array<string, mixed>}  $result
     */
    private function resultHasSensitive(array $result): bool
    {
        return $this->resultSensitiveKeys($result) !== [];
    }

    /**
     * @param  array{meta?: array<string, mixed>}  $result
     * @return list<string>
     */
    private function resultSensitiveKeys(array $result): array
    {
        $keys = [];
        $hidden = $result['meta']['hidden_fields'] ?? [];
        if (is_array($hidden)) {
            foreach ($hidden as $h) {
                if (is_array($h) && isset($h['key']) && is_string($h['key'])) {
                    $keys[] = $h['key'];
                }
            }
        }
        $datasetKey = $result['meta']['dataset'] ?? null;
        $selected = $result['meta']['fields'] ?? [];
        if (is_string($datasetKey) && $this->registry->has($datasetKey) && is_array($selected)) {
            $companyId = (int) (request()?->user()?->company_id ?? 0);
            foreach ($this->registry->get($datasetKey)->fieldsForCompany($companyId) as $f) {
                if (in_array($f->key, $selected, true) && $f->isClassifiedSensitive()) {
                    $keys[] = $f->key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function assertDataset(mixed $key): void
    {
        if (! is_string($key) || ! $this->registry->has($key)) {
            throw ValidationException::withMessages(['dataset_key' => ['Geçersiz dataset']]);
        }
    }

    /**
     * statement_timeout + motor çalıştırma (SQL motoruna dokunmadan sarmalayıcı).
     *
     * @param  array<string, mixed>  $config
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function runWithGuards(User $user, int $companyId, array $config): array
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $companyId, $config) {
                if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
                    \Illuminate\Support\Facades\DB::statement(
                        'SET LOCAL statement_timeout = '.(int) ReportResultCache::STATEMENT_TIMEOUT_MS
                    );
                }

                return $this->builder->run($user, $companyId, $config);
            });
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'statement timeout') || str_contains($msg, 'canceling statement')) {
                throw ValidationException::withMessages([
                    'query' => ['Rapor sorgusu zaman aşımına uğradı. Filtreleri daraltın veya aggregasyon kullanın.'],
                ]);
            }
            throw $e;
        }
    }

    /**
     * Sistem raporunu firmaya kopyala (özelleştirilebilir).
     */
    public function cloneForCompany(SavedReport $source, User $user, int $companyId, ?string $name = null): SavedReport
    {
        if (! $source->isAccessibleBy($user)) {
            abort(403, 'Bu rapora erişim yok');
        }
        if (! $this->belongsToCompanyOrSystem($source, $companyId)) {
            abort(404, 'Rapor bulunamadı');
        }

        $copy = SavedReport::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'name' => $name ?? ($source->name.' (kopya)'),
            'description' => $source->description,
            'dataset_key' => $source->dataset_key,
            'module_key' => $source->module_key,
            'system_key' => null,
            'config' => $source->config,
            'is_system' => false,
            'is_shared' => false,
            'is_favorite' => false,
            'cache_ttl_seconds' => $source->cache_ttl_seconds,
        ]);

        return $copy->fresh(['shares']);
    }

    private function belongsToCompanyOrSystem(SavedReport $report, int $companyId): bool
    {
        if ($report->is_system && $report->company_id === null) {
            return true;
        }

        return (int) $report->company_id === $companyId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $data): array
    {
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        if (isset($data['fields'])) {
            $config['fields'] = $data['fields'];
        }
        if (isset($data['filters'])) {
            $config['filters'] = $data['filters'];
        }
        if (isset($data['group_by'])) {
            $config['group_by'] = $data['group_by'];
        }
        if (isset($data['aggregations'])) {
            $config['aggregations'] = $data['aggregations'];
        }
        if (isset($data['sorts'])) {
            $config['sorts'] = $data['sorts'];
        }
        if (isset($data['joins'])) {
            $config['joins'] = $data['joins'];
        }
        $config['dataset'] = $data['dataset_key'] ?? $config['dataset'] ?? null;

        return $config;
    }
}

