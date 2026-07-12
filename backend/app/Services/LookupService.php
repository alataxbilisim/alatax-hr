<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\Lookup;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Lookup Engine — firma + sistem birleşik okuma, resolve (K-A), kullanım kontrolü (K-B).
 */
class LookupService
{
    public const TYPE_EMPLOYEE_STATUS = 'employee_status';

    public const TYPE_WORK_TYPE = 'work_type';

    public const TYPE_CURRENCY = 'currency';

    public const TYPE_CITY_TR = 'city_tr';

    public const TYPE_BLOOD_TYPE = 'blood_type';

    public const TYPE_COUNTRY = 'country';

    /** @var array<string, list<array{model: class-string, column: string}>> */
    private const USAGE_MAP = [
        self::TYPE_EMPLOYEE_STATUS => [
            ['model' => Employee::class, 'column' => 'status'],
        ],
        self::TYPE_WORK_TYPE => [
            ['model' => Employee::class, 'column' => 'work_type'],
            ['model' => JobPosition::class, 'column' => 'employment_type'],
        ],
    ];

    /**
     * Firma + sistem birleşik değerler (aynı value'da firma override öncelikli).
     *
     * @return Collection<int, Lookup>
     */
    public function forType(string $type, ?int $companyId, bool $activeOnly = true): Collection
    {
        $defaults = Lookup::query()
            ->whereNull('company_id')
            ->ofType($type)
            ->get()
            ->keyBy('value');

        $companyRows = collect();
        if ($companyId !== null) {
            $companyRows = Lookup::query()
                ->where('company_id', $companyId)
                ->ofType($type)
                ->get()
                ->keyBy('value');
        }

        $merged = collect();
        foreach ($defaults as $value => $row) {
            $merged->put($value, $companyRows->get($value, $row));
        }
        foreach ($companyRows as $value => $row) {
            if (! $merged->has($value)) {
                $merged->put($value, $row);
            }
        }

        if ($activeOnly) {
            $merged = $merged->filter(fn (Lookup $row) => $row->is_active);
        }

        return $merged
            ->values()
            ->sortBy([
                ['sort_order', 'asc'],
                ['label', 'asc'],
            ])
            ->values();
    }

    /**
     * K-A: value → label/color (pasif değerler dahil — eski kayıt görüntüleme).
     *
     * @return array{value: string, label: string, color: ?string, is_active: bool}|null
     */
    public function resolve(string $type, ?string $value, ?int $companyId): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $row = $this->forType($type, $companyId, activeOnly: false)
            ->firstWhere('value', $value);

        if (! $row) {
            return [
                'value' => $value,
                'label' => $value,
                'color' => null,
                'is_active' => false,
            ];
        }

        return [
            'value' => $row->value,
            'label' => $row->label,
            'color' => $row->color,
            'is_active' => $row->is_active,
        ];
    }

    public function resolveLabel(string $type, ?string $value, ?int $companyId): ?string
    {
        return $this->resolve($type, $value, $companyId)['label'] ?? null;
    }

    public function isValidValue(string $type, string $value, ?int $companyId, bool $activeOnly = true): bool
    {
        return $this->forType($type, $companyId, $activeOnly)
            ->contains(fn (Lookup $row) => $row->value === $value);
    }

    /**
     * @throws ValidationException
     */
    public function assertValid(string $type, ?string $value, ?int $companyId, string $attribute): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! $this->isValidValue($type, $value, $companyId, activeOnly: true)) {
            throw ValidationException::withMessages([
                $attribute => ["Geçersiz {$attribute} değeri: {$value}"],
            ]);
        }
    }

    public function isUsed(string $type, string $value, int $companyId): bool
    {
        $checks = self::USAGE_MAP[$type] ?? [];

        foreach ($checks as $check) {
            $model = $check['model'];
            $column = $check['column'];
            $exists = $model::query()
                ->where('company_id', $companyId)
                ->where($column, $value)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Firma satırı bul veya platform default'tan override oluştur.
     */
    public function findEditableRow(string $type, string $value, int $companyId): ?Lookup
    {
        $companyRow = Lookup::query()
            ->where('company_id', $companyId)
            ->ofType($type)
            ->where('value', $value)
            ->first();

        if ($companyRow) {
            return $companyRow;
        }

        return Lookup::query()
            ->whereNull('company_id')
            ->ofType($type)
            ->where('value', $value)
            ->first();
    }

    /**
     * Platform default'u mutate etme — firma override kopyası oluştur.
     */
    public function ensureCompanyRow(Lookup $source, int $companyId, array $overrides = []): Lookup
    {
        if ($source->company_id === $companyId) {
            $source->fill($overrides);
            $source->save();

            return $source->fresh();
        }

        $existing = Lookup::query()
            ->where('company_id', $companyId)
            ->ofType($source->lookup_type)
            ->where('value', $source->value)
            ->first();

        if ($existing) {
            $existing->fill($overrides);
            $existing->save();

            return $existing->fresh();
        }

        return Lookup::create(array_merge([
            'company_id' => $companyId,
            'lookup_type' => $source->lookup_type,
            'value' => $source->value,
            'label' => $source->label,
            'color' => $source->color,
            'sort_order' => $source->sort_order,
            'is_active' => $source->is_active,
            'is_system' => false,
            'parent_lookup_id' => $source->parent_lookup_id,
            'meta' => $source->meta,
        ], $overrides));
    }
}
