<?php

namespace App\Services\Reports;

use App\Enums\DataScopeLevel;
use App\Models\User;
use App\Services\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Whitelist'li güvenli rapor sorgu motoru.
 * Kullanıcı girdileri ASLA SQL tanımlayıcı olarak kullanılmaz.
 */
class ReportQueryBuilder
{
    public const OPERATORS = [
        'eq', 'neq', 'gt', 'gte', 'lt', 'lte',
        'between', 'in', 'like', 'is_null', 'is_not_null', 'date_range',
    ];

    public const AGGREGATIONS = ['count', 'sum', 'avg', 'min', 'max'];

    /** Önizleme / run üst sınırı (D1a). */
    public const PREVIEW_MAX_ROWS = 1000;

    /** Export üst sınırı (D1b) — yalnızca __export=true ile. */
    public const EXPORT_MAX_ROWS = 50000;

    public function __construct(
        protected DatasetRegistry $registry,
        protected DataScopeService $dataScope,
    ) {}

    /**
     * @param  array{
     *   dataset: string,
     *   fields?: list<string>,
     *   filters?: list<array{field: string, op: string, value?: mixed}>,
     *   group_by?: list<string>,
     *   aggregations?: list<array{field: string, fn: string, alias?: string}>,
     *   sorts?: list<array{field: string, dir?: string}>,
     *   joins?: list<string>,
     *   limit?: int,
     *   offset?: int
     * }  $config
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function run(User $user, int $companyId, array $config): array
    {
        $datasetKey = (string) ($config['dataset'] ?? '');
        if ($datasetKey === '' || ! $this->registry->has($datasetKey)) {
            throw new InvalidArgumentException('Geçersiz dataset');
        }

        $dataset = $this->registry->get($datasetKey);
        $allowedFields = $dataset->filterAllowedFields(
            $dataset->fieldsForCompany($companyId),
            $user
        );
        $fieldMap = [];
        foreach ($allowedFields as $f) {
            $fieldMap[$f->key] = $f;
        }

        $requestedFields = $config['fields'] ?? array_map(fn (ReportField $f) => $f->key, $allowedFields);
        if (! is_array($requestedFields) || $requestedFields === []) {
            throw new InvalidArgumentException('En az bir alan seçilmelidir');
        }

        $selectFields = [];
        foreach ($requestedFields as $key) {
            if (! is_string($key) || ! isset($fieldMap[$key])) {
                // İzinsiz / bilinmeyen alan — sessizce düşür (403 değil)
                continue;
            }
            $selectFields[] = $fieldMap[$key];
        }
        if ($selectFields === []) {
            throw new InvalidArgumentException('Seçilebilir alan bulunamadı (yetki veya whitelist)');
        }

        $query = $dataset->newQuery();
        $table = $dataset->table();
        $query->from($table);

        $this->applyJoins($query, $dataset, $config['joins'] ?? [], $selectFields, $config);
        $this->applyDataScope($query, $dataset, $user);

        $filters = $config['filters'] ?? [];
        if (! is_array($filters)) {
            throw new InvalidArgumentException('filters dizi olmalıdır');
        }
        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }
            $this->applyFilter($query, $filter, $fieldMap);
        }

        $groupBy = $config['group_by'] ?? [];
        $aggregations = $config['aggregations'] ?? [];
        $isAggregate = (is_array($groupBy) && $groupBy !== []) || (is_array($aggregations) && $aggregations !== []);

        if ($isAggregate) {
            $this->applyAggregateSelect($query, $selectFields, $groupBy, $aggregations, $fieldMap);
        } else {
            $this->applyPlainSelect($query, $selectFields);
        }

        $sorts = $config['sorts'] ?? [];
        if (! is_array($sorts) || $sorts === []) {
            foreach ($dataset->defaultSort() as $sk) {
                if (isset($fieldMap[$sk])) {
                    $sorts[] = ['field' => $sk, 'dir' => 'asc'];
                }
            }
        }
        $this->applySorts($query, $sorts, $fieldMap, $isAggregate);

        // __export yalnızca servis katmanından set edilir; client doğrulamasında yok.
        $forExport = ($config['__export'] ?? false) === true;
        $maxCap = $forExport ? self::EXPORT_MAX_ROWS : self::PREVIEW_MAX_ROWS;
        $defaultLimit = $forExport ? self::EXPORT_MAX_ROWS : 100;
        $limit = min(max((int) ($config['limit'] ?? $defaultLimit), 1), $maxCap);
        $offset = $forExport ? 0 : max((int) ($config['offset'] ?? 0), 0);

        $fetchLimit = $forExport ? $limit + 1 : $limit;
        $query->limit($fetchLimit)->offset($offset);

        $rows = $query->toBase()->get()->map(fn ($row) => (array) $row)->all();
        $truncated = false;
        if ($forExport && count($rows) > $limit) {
            $truncated = true;
            array_pop($rows);
        }

        $meta = [
            'dataset' => $datasetKey,
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($rows),
            'fields' => array_map(fn (ReportField $f) => $f->key, $selectFields),
            'data_scope' => $this->dataScope->resolve($user)->value,
        ];
        if ($forExport) {
            $meta['truncated'] = $truncated;
            $meta['export_max'] = self::EXPORT_MAX_ROWS;
        }

        return [
            'rows' => $rows,
            'meta' => $meta,
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<ReportField>  $selectFields
     * @param  list<string>|mixed  $requestedJoins
     * @param  array<string, mixed>  $config
     */
    private function applyJoins(Builder $query, AbstractDataset $dataset, mixed $requestedJoins, array $selectFields, array $config): void
    {
        $allowed = [];
        foreach ($dataset->allowedJoins() as $join) {
            $allowed[$join['key']] = $join;
        }

        $needed = [];
        if (is_array($requestedJoins)) {
            foreach ($requestedJoins as $jk) {
                if (is_string($jk) && isset($allowed[$jk])) {
                    $needed[$jk] = $allowed[$jk];
                }
            }
        }

        // Alan/sort/filter için join kolonları gerekliyse otomatik ekle
        $allKeys = array_merge(
            array_map(fn (ReportField $f) => $f->key, $selectFields),
            is_array($config['group_by'] ?? null) ? $config['group_by'] : [],
        );
        foreach ($selectFields as $field) {
            if (str_starts_with($field->column, 'departments.') && isset($allowed['departments'])) {
                $needed['departments'] = $allowed['departments'];
            }
            if (str_starts_with($field->column, 'branches.') && isset($allowed['branches'])) {
                $needed['branches'] = $allowed['branches'];
            }
        }
        unset($allKeys);

        foreach ($needed as $join) {
            $type = $join['type'] ?? 'left';
            if ($type === 'inner') {
                $query->join($join['table'], $join['first'], $join['operator'], $join['second']);
            } else {
                $query->leftJoin($join['table'], $join['first'], $join['operator'], $join['second']);
            }
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyDataScope(Builder $query, AbstractDataset $dataset, User $user): void
    {
        $mode = $dataset->dataScopeMode();
        $scope = $this->dataScope->resolve($user);

        match ($mode) {
            'employee' => $this->dataScope->scopeForEmployee($query, $user),
            'user' => $this->dataScope->scopeForUser($query, $user, 'user_id'),
            'assigned_to' => $scope === DataScopeLevel::Company
                ? $query
                : $this->dataScope->scopeForUser($query, $user, 'assigned_to'),
            default => $query,
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array{field?: mixed, op?: mixed, value?: mixed}  $filter
     * @param  array<string, ReportField>  $fieldMap
     */
    private function applyFilter(Builder $query, array $filter, array $fieldMap): void
    {
        $fieldKey = $filter['field'] ?? null;
        $op = $filter['op'] ?? null;
        if (! is_string($fieldKey) || ! is_string($op)) {
            throw new InvalidArgumentException('Geçersiz filtre');
        }
        if (! isset($fieldMap[$fieldKey])) {
            throw new InvalidArgumentException('Filtre alanı yetkisiz veya geçersiz: '.$fieldKey);
        }
        if (! in_array($op, self::OPERATORS, true)) {
            throw new InvalidArgumentException('İzin verilmeyen operatör: '.$op);
        }

        $field = $fieldMap[$fieldKey];
        $expr = $this->sqlExpression($field);
        $value = $filter['value'] ?? null;

        match ($op) {
            'eq' => $query->whereRaw("{$expr} = ?", [$value]),
            'neq' => $query->whereRaw("{$expr} <> ?", [$value]),
            'gt' => $query->whereRaw("{$expr} > ?", [$value]),
            'gte' => $query->whereRaw("{$expr} >= ?", [$value]),
            'lt' => $query->whereRaw("{$expr} < ?", [$value]),
            'lte' => $query->whereRaw("{$expr} <= ?", [$value]),
            'like' => $query->whereRaw("{$expr}::text ILIKE ?", ['%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $value).'%']),
            'is_null' => $query->whereRaw("{$expr} IS NULL"),
            'is_not_null' => $query->whereRaw("{$expr} IS NOT NULL"),
            'between' => $this->applyBetween($query, $expr, $value),
            'date_range' => $this->applyBetween($query, $expr, $value),
            'in' => $this->applyIn($query, $expr, $value),
            default => throw new InvalidArgumentException('Operatör desteklenmiyor'),
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyBetween(Builder $query, string $expr, mixed $value): void
    {
        if (! is_array($value) || count($value) < 2) {
            throw new InvalidArgumentException('between/date_range iki değer ister');
        }
        $query->whereRaw("{$expr} BETWEEN ? AND ?", [$value[0], $value[1]]);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyIn(Builder $query, string $expr, mixed $value): void
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException('in operatörü dizi ister');
        }
        if (count($value) > 100) {
            throw new InvalidArgumentException('in listesi çok uzun');
        }
        $placeholders = implode(',', array_fill(0, count($value), '?'));
        $query->whereRaw("{$expr} IN ({$placeholders})", array_values($value));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<ReportField>  $selectFields
     */
    private function applyPlainSelect(Builder $query, array $selectFields): void
    {
        $parts = [];
        foreach ($selectFields as $field) {
            $parts[] = DB::raw($this->sqlExpression($field).' as '.$this->quoteAlias($field->key));
        }
        $query->select($parts);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<ReportField>  $selectFields
     * @param  mixed  $groupBy
     * @param  mixed  $aggregations
     * @param  array<string, ReportField>  $fieldMap
     */
    private function applyAggregateSelect(Builder $query, array $selectFields, mixed $groupBy, mixed $aggregations, array $fieldMap): void
    {
        $selects = [];
        $groupExprs = [];

        if (is_array($groupBy)) {
            foreach ($groupBy as $gk) {
                if (! is_string($gk) || ! isset($fieldMap[$gk])) {
                    throw new InvalidArgumentException('Geçersiz group_by: '.(string) $gk);
                }
                $f = $fieldMap[$gk];
                $expr = $this->sqlExpression($f);
                $selects[] = DB::raw("{$expr} as ".$this->quoteAlias($f->key));
                $groupExprs[] = $expr;
            }
        }

        if (is_array($aggregations)) {
            foreach ($aggregations as $agg) {
                if (! is_array($agg)) {
                    continue;
                }
                $fn = strtolower((string) ($agg['fn'] ?? ''));
                $fieldKey = $agg['field'] ?? null;
                if (! in_array($fn, self::AGGREGATIONS, true)) {
                    throw new InvalidArgumentException('İzin verilmeyen aggregation: '.$fn);
                }
                if ($fn === 'count' && ($fieldKey === '*' || $fieldKey === null)) {
                    $alias = is_string($agg['alias'] ?? null) ? $agg['alias'] : 'count_all';
                    $this->assertSafeAlias($alias);
                    $selects[] = DB::raw('COUNT(*) as '.$this->quoteAlias($alias));
                    continue;
                }
                if (! is_string($fieldKey) || ! isset($fieldMap[$fieldKey])) {
                    throw new InvalidArgumentException('Aggregation alanı yetkisiz: '.(string) $fieldKey);
                }
                $f = $fieldMap[$fieldKey];
                if ($fn !== 'count' && ! $f->isMeasure()) {
                    throw new InvalidArgumentException('Ölçü olmayan alana aggregation uygulanamaz: '.$fieldKey);
                }
                $alias = is_string($agg['alias'] ?? null) ? $agg['alias'] : $fn.'_'.$fieldKey;
                $this->assertSafeAlias($alias);
                $expr = $this->sqlExpression($f);
                $selects[] = DB::raw(strtoupper($fn)."({$expr}) as ".$this->quoteAlias($alias));
            }
        }

        if ($selects === []) {
            // group yoksa en azından seçili dimension'ları göster
            foreach ($selectFields as $field) {
                if ($field->isDimension()) {
                    $expr = $this->sqlExpression($field);
                    $selects[] = DB::raw("{$expr} as ".$this->quoteAlias($field->key));
                    $groupExprs[] = $expr;
                }
            }
            $selects[] = DB::raw('COUNT(*) as '.$this->quoteAlias('count_all'));
        }

        $query->select($selects);
        if ($groupExprs !== []) {
            $query->groupByRaw(implode(', ', $groupExprs));
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  mixed  $sorts
     * @param  array<string, ReportField>  $fieldMap
     */
    private function applySorts(Builder $query, mixed $sorts, array $fieldMap, bool $isAggregate): void
    {
        if (! is_array($sorts)) {
            return;
        }
        foreach ($sorts as $sort) {
            if (! is_array($sort)) {
                continue;
            }
            $fieldKey = $sort['field'] ?? null;
            if (! is_string($fieldKey) || ! isset($fieldMap[$fieldKey])) {
                continue;
            }
            $dir = strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $expr = $this->sqlExpression($fieldMap[$fieldKey]);
            $query->orderByRaw("{$expr} {$dir}");
        }
    }

    private function sqlExpression(ReportField $field): string
    {
        if ($field->isCustom && $field->customJsonKey !== null && $field->customJsonColumn !== null) {
            $col = $field->customJsonColumn;
            $key = $field->customJsonKey;
            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $col) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException('Geçersiz custom alan');
            }
            $table = explode('.', $field->column)[0] ?? '';
            if ($table !== '' && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $table)) {
                return "({$table}.{$col}->>'{$key}')";
            }

            return "({$col}->>'{$key}')";
        }

        // Whitelist kolon: table.column
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/', $field->column)) {
            throw new InvalidArgumentException('Geçersiz kolon ifadesi');
        }

        return $field->column;
    }

    private function quoteAlias(string $alias): string
    {
        $this->assertSafeAlias($alias);

        return '"'.$alias.'"';
    }

    private function assertSafeAlias(string $alias): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Geçersiz alias');
        }
    }

    /**
     * D1c pivot — DataScope'u dışarıdan uygula.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public function applyExternalScope(Builder $query, AbstractDataset $dataset, User $user): void
    {
        $this->applyDataScope($query, $dataset, $user);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array{field?: mixed, op?: mixed, value?: mixed}  $filter
     * @param  array<string, ReportField>  $fieldMap
     */
    public function applyExternalFilter(Builder $query, array $filter, array $fieldMap): void
    {
        $this->applyFilter($query, $filter, $fieldMap);
    }

    public function externalSqlExpression(ReportField $field): string
    {
        return $this->sqlExpression($field);
    }
}
