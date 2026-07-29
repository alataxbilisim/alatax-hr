<?php

namespace App\Services\Reports;

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
    ) {}

    public function listFor(User $user, int $companyId, int $perPage = 20): LengthAwarePaginator
    {
        return SavedReport::query()
            ->where('company_id', $companyId)
            ->whereNotNull('dataset_key')
            ->accessibleBy($user)
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

        return SavedReport::create([
            'company_id' => $companyId,
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
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SavedReport $report, User $user, array $data): SavedReport
    {
        if ((int) $report->user_id !== (int) $user->id && ! $user->can('reports.definitions.edit')) {
            throw ValidationException::withMessages(['id' => ['Bu raporu düzenleme yetkiniz yok']]);
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
        $report->save();

        return $report->fresh();
    }

    public function delete(SavedReport $report, User $user): void
    {
        if ($report->is_system) {
            throw ValidationException::withMessages(['id' => ['Sistem raporu silinemez']]);
        }
        if ((int) $report->user_id !== (int) $user->id && ! $user->can('reports.definitions.delete')) {
            throw ValidationException::withMessages(['id' => ['Bu raporu silme yetkiniz yok']]);
        }
        $report->delete();
    }

    /**
     * Kaydedilmiş raporu çalıştır — viewer kapsamı (sahip miras alınmaz).
     *
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function run(SavedReport $report, User $viewer, int $companyId, array $overrides = []): array
    {
        if (! $report->isAccessibleBy($viewer)) {
            throw ValidationException::withMessages(['id' => ['Bu rapora erişim yok']]);
        }
        if ((int) $report->company_id !== $companyId) {
            throw ValidationException::withMessages(['id' => ['Rapor bulunamadı']]);
        }

        $config = is_array($report->config) ? $report->config : [];
        $config['dataset'] = $report->dataset_key;
        $config = array_merge($config, $overrides);
        $config['dataset'] = $report->dataset_key;

        return $this->builder->run($viewer, $companyId, $config);
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

        return $this->builder->run($user, $companyId, $config);
    }

    /**
     * Export: query builder üzerinden (istemci sayfası değil), üst sınır 50k.
     *
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

        return $this->builder->run($user, $companyId, $config);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function exportSaved(SavedReport $report, User $viewer, int $companyId): array
    {
        if (! $report->isAccessibleBy($viewer)) {
            throw ValidationException::withMessages(['id' => ['Bu rapora erişim yok']]);
        }
        if ((int) $report->company_id !== $companyId) {
            throw ValidationException::withMessages(['id' => ['Rapor bulunamadı']]);
        }

        $config = is_array($report->config) ? $report->config : [];
        $config['dataset'] = $report->dataset_key;

        return $this->export($viewer, $companyId, $config);
    }

    private function assertDataset(mixed $key): void
    {
        if (! is_string($key) || ! $this->registry->has($key)) {
            throw ValidationException::withMessages(['dataset_key' => ['Geçersiz dataset']]);
        }
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
