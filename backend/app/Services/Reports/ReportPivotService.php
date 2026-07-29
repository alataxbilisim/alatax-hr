<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Expression\MeasureExpressionCompiler;
use App\Services\Reports\Expression\MeasureExpressionParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pivot motoru — GROUP BY + uygulama katmanında matris (Postgres crosstab YOK).
 *
 * Gerekçe: taşınabilirlik, mevcut whitelist builder ile aynı güvenlik yolu,
 * kardinalite kontrolü ve alt toplam PHP tarafında tutarlı.
 */
class ReportPivotService
{
    public const MAX_COLUMN_CARDINALITY = 100;

    public const EMPTY_LABEL = '(boş)';

    public const DATE_GRAINS = ['year', 'quarter', 'month', 'week', 'day'];

    public function __construct(
        protected DatasetRegistry $registry,
        protected ReportQueryBuilder $builder,
        protected MeasureExpressionParser $parser,
        protected MeasureExpressionCompiler $compiler,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function pivot(User $user, int $companyId, array $config): array
    {
        $datasetKey = (string) ($config['dataset'] ?? '');
        if ($datasetKey === '' || ! $this->registry->has($datasetKey)) {
            throw new InvalidArgumentException('Geçersiz dataset');
        }

        $dataset = $this->registry->get($datasetKey);
        $fieldMap = $this->allowedFieldMap($dataset, $user, $companyId);

        $rows = $this->normalizeDims($config['rows'] ?? [], $fieldMap, 1, 3, 'satır');
        $cols = $this->normalizeDims($config['columns'] ?? [], $fieldMap, 0, 2, 'sütun');
        $measures = $this->normalizeMeasures($config['measures'] ?? [], $fieldMap);
        $wantSubtotals = (bool) ($config['subtotals'] ?? false);
        $wantGrand = (bool) ($config['grand_total'] ?? false);

        if ($cols !== []) {
            $this->assertColumnCardinality($dataset, $user, $companyId, $cols, $config['filters'] ?? [], $fieldMap);
        }

        $groupDims = array_merge($rows, $cols);
        $flat = $this->runGrouped($dataset, $user, $companyId, $groupDims, $measures, $config['filters'] ?? [], $fieldMap);

        $colKeys = $this->uniqueKeyCombos($flat, $cols);
        if (count($colKeys) > self::MAX_COLUMN_CARDINALITY) {
            throw new InvalidArgumentException(
                'Bu alan sütun olarak çok fazla değer üretiyor (max '.self::MAX_COLUMN_CARDINALITY.')'
            );
        }

        $rowKeys = $this->uniqueKeyCombos($flat, $rows);
        $limit = min(max((int) ($config['limit'] ?? 500), 1), ReportQueryBuilder::PREVIEW_MAX_ROWS);
        if (count($rowKeys) > $limit) {
            $rowKeys = array_slice($rowKeys, 0, $limit);
        }

        $index = $this->indexFlat($flat, $rows, $cols, $measures);
        $cells = [];
        foreach ($rowKeys as $ri => $rk) {
            foreach ($colKeys as $ci => $ck) {
                $key = $this->comboKey(array_merge($rk, $ck));
                foreach ($measures as $mi => $m) {
                    $cells[] = [
                        'row' => $ri,
                        'col' => $ci,
                        'measure' => $m['alias'],
                        'value' => $index[$key][$m['alias']] ?? null,
                    ];
                }
            }
        }

        $result = [
            'row_headers' => array_map(fn ($d) => [
                'field' => $d['field'],
                'grain' => $d['grain'],
                'label' => $fieldMap[$d['field']]->label,
            ], $rows),
            'column_headers' => array_map(fn ($d) => [
                'field' => $d['field'],
                'grain' => $d['grain'],
                'label' => $fieldMap[$d['field']]->label,
            ], $cols),
            'row_keys' => array_map(fn ($rk) => $this->labelCombo($rk), $rowKeys),
            'column_keys' => array_map(fn ($ck) => $this->labelCombo($ck), $colKeys),
            'measures' => array_map(fn ($m) => [
                'alias' => $m['alias'],
                'format' => $m['format'],
                'decimals' => $m['decimals'],
            ], $measures),
            'cells' => $cells,
            'meta' => [
                'dataset' => $datasetKey,
                'row_count' => count($rowKeys),
                'column_count' => count($colKeys),
                'data_scope' => app(\App\Services\DataScopeService::class)->resolve($user)->value,
            ],
        ];

        if ($wantSubtotals && $rows !== []) {
            $result['subtotals'] = $this->computeSubtotals($index, $rowKeys, $colKeys, $rows, $cols, $measures);
        }
        if ($wantGrand) {
            $result['grand_total'] = $this->computeGrand($index, $rowKeys, $colKeys, $measures);
        }

        return $result;
    }

    /**
     * Hücre tıklaması: bir seviye aşağı veya detay satırları (query builder).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function drill(User $user, int $companyId, array $config): array
    {
        $mode = (string) ($config['mode'] ?? 'details');
        $datasetKey = (string) ($config['dataset'] ?? '');
        if (! $this->registry->has($datasetKey)) {
            throw new InvalidArgumentException('Geçersiz dataset');
        }
        $dataset = $this->registry->get($datasetKey);
        $fieldMap = $this->allowedFieldMap($dataset, $user, $companyId);

        $cellFilters = $config['cell_filters'] ?? [];
        if (! is_array($cellFilters)) {
            throw new InvalidArgumentException('cell_filters dizi olmalıdır');
        }

        $baseFilters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $mergedFilters = array_merge($baseFilters, $this->normalizeCellFilters($cellFilters, $fieldMap));

        if ($mode === 'details') {
            $fields = $config['fields'] ?? array_keys($fieldMap);
            if (! is_array($fields)) {
                throw new InvalidArgumentException('fields dizi olmalıdır');
            }

            return $this->builder->run($user, $companyId, [
                'dataset' => $datasetKey,
                'fields' => $fields,
                'filters' => $mergedFilters,
                'limit' => min(max((int) ($config['limit'] ?? 50), 1), 200),
                'offset' => max((int) ($config['offset'] ?? 0), 0),
            ]);
        }

        // next level — hierarchy
        $hierarchyKey = (string) ($config['hierarchy_key'] ?? 'org');
        $hierarchies = $dataset->hierarchies();
        if (! isset($hierarchies[$hierarchyKey])) {
            throw new InvalidArgumentException('Bilinmeyen hiyerarşi: '.$hierarchyKey);
        }
        $levels = $hierarchies[$hierarchyKey]['levels'];
        $level = (int) ($config['level'] ?? 0);
        if ($level < 0 || $level >= count($levels) - 1) {
            throw new InvalidArgumentException('Daha fazla drill seviyesi yok');
        }
        $nextField = $levels[$level + 1];

        $measure = $config['measure'] ?? ['fn' => 'count', 'field' => '*', 'alias' => 'count_all'];
        $measures = $this->normalizeMeasures([$measure], $fieldMap);

        $rowDim = $this->normalizeDims([['field' => $nextField]], $fieldMap, 1, 1, 'drill');
        $flat = $this->runGrouped($dataset, $user, $companyId, $rowDim, $measures, $mergedFilters, $fieldMap);

        return [
            'level' => $level + 1,
            'hierarchy_key' => $hierarchyKey,
            'field' => $nextField,
            'rows' => $flat,
            'meta' => [
                'dataset' => $datasetKey,
                'count' => count($flat),
            ],
        ];
    }

    /**
     * @return array<string, ReportField>
     */
    private function allowedFieldMap(AbstractDataset $dataset, User $user, int $companyId): array
    {
        $allowed = $dataset->filterAllowedFields($dataset->fieldsForCompany($companyId), $user);
        $map = [];
        foreach ($allowed as $f) {
            $map[$f->key] = $f;
        }

        return $map;
    }

    /**
     * @param  mixed  $dims
     * @param  array<string, ReportField>  $fieldMap
     * @return list<array{field: string, grain: ?string}>
     */
    private function normalizeDims(mixed $dims, array $fieldMap, int $min, int $max, string $label): array
    {
        if (! is_array($dims)) {
            throw new InvalidArgumentException("{$label} boyutları dizi olmalıdır");
        }
        if (count($dims) < $min || count($dims) > $max) {
            throw new InvalidArgumentException("{$label} boyutu {$min}-{$max} arasında olmalı");
        }
        $out = [];
        foreach ($dims as $d) {
            if (! is_array($d) || ! is_string($d['field'] ?? null)) {
                throw new InvalidArgumentException("Geçersiz {$label} boyutu");
            }
            $key = $d['field'];
            if (! isset($fieldMap[$key])) {
                throw new InvalidArgumentException("Boyut alanı yetkisiz: {$key}");
            }
            $grain = isset($d['grain']) && is_string($d['grain']) ? $d['grain'] : null;
            if ($grain !== null) {
                if (! in_array($grain, self::DATE_GRAINS, true)) {
                    throw new InvalidArgumentException('Geçersiz tarih kırılımı: '.$grain);
                }
                if ($fieldMap[$key]->type !== 'date' && $fieldMap[$key]->type !== 'datetime') {
                    throw new InvalidArgumentException('Tarih kırılımı yalnız date alanlarında: '.$key);
                }
            }
            $out[] = ['field' => $key, 'grain' => $grain];
        }

        return $out;
    }

    /**
     * @param  mixed  $measures
     * @param  array<string, ReportField>  $fieldMap
     * @return list<array{alias: string, format: string, decimals: int, sql: string, bindings: list<mixed>}>
     */
    private function normalizeMeasures(mixed $measures, array $fieldMap): array
    {
        if (! is_array($measures) || $measures === []) {
            throw new InvalidArgumentException('En az bir ölçü gerekli');
        }
        if (count($measures) > 10) {
            throw new InvalidArgumentException('Çok fazla ölçü');
        }
        $out = [];
        foreach ($measures as $i => $m) {
            if (! is_array($m)) {
                continue;
            }
            $alias = is_string($m['alias'] ?? null) ? $m['alias'] : ('m'.$i);
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
                throw new InvalidArgumentException('Geçersiz ölçü alias');
            }
            $format = is_string($m['format'] ?? null) ? $m['format'] : 'number';
            if (! in_array($format, ['number', 'money', 'percent'], true)) {
                $format = 'number';
            }
            $decimals = min(max((int) ($m['decimals'] ?? 2), 0), 6);

            if (is_string($m['expression'] ?? null) && $m['expression'] !== '') {
                $ast = $this->parser->parse($m['expression']);
                // İzin sızıntısı: referans alanlar fieldMap'te olmalı
                foreach ($this->parser->referencedFields($ast) as $ref) {
                    if (! isset($fieldMap[$ref])) {
                        throw new InvalidArgumentException('Formül izinsiz alana referans veriyor: '.$ref);
                    }
                }
                $compiled = $this->compiler->compile(
                    $ast,
                    $fieldMap,
                    fn (ReportField $f) => $this->sqlExpr($f, null)
                );
                $out[] = [
                    'alias' => $alias,
                    'format' => $format,
                    'decimals' => $decimals,
                    'sql' => $compiled['sql'],
                    'bindings' => $compiled['bindings'],
                ];
            } else {
                $fn = strtolower((string) ($m['fn'] ?? 'count'));
                $field = $m['field'] ?? '*';
                $expr = $this->simpleAggSql($fn, $field, $fieldMap);
                $out[] = [
                    'alias' => $alias,
                    'format' => $format,
                    'decimals' => $decimals,
                    'sql' => $expr,
                    'bindings' => [],
                ];
            }
        }
        if ($out === []) {
            throw new InvalidArgumentException('Geçerli ölçü yok');
        }

        return $out;
    }

    /**
     * @param  array<string, ReportField>  $fieldMap
     */
    private function simpleAggSql(string $fn, mixed $field, array $fieldMap): string
    {
        $allowed = ['count', 'sum', 'avg', 'min', 'max', 'count_distinct'];
        if (! in_array($fn, $allowed, true)) {
            throw new InvalidArgumentException('İzin verilmeyen aggregation: '.$fn);
        }
        if ($fn === 'count' && ($field === '*' || $field === null)) {
            return 'COUNT(*)';
        }
        if (! is_string($field) || ! isset($fieldMap[$field])) {
            throw new InvalidArgumentException('Aggregation alanı yetkisiz: '.(string) $field);
        }
        $f = $fieldMap[$field];
        if (in_array($fn, ['sum', 'avg', 'min', 'max'], true) && ! $f->isMeasure()) {
            throw new InvalidArgumentException('Ölçü olmayan alana aggregation uygulanamaz: '.$field);
        }
        $expr = $this->sqlExpr($f, null);

        return match ($fn) {
            'count' => "COUNT({$expr})",
            'count_distinct' => "COUNT(DISTINCT {$expr})",
            'sum' => "SUM({$expr})",
            'avg' => "AVG({$expr})",
            'min' => "MIN({$expr})",
            'max' => "MAX({$expr})",
            default => throw new InvalidArgumentException('Aggregation desteklenmiyor'),
        };
    }

    /**
     * @param  list<array{field: string, grain: ?string}>  $cols
     * @param  mixed  $filters
     * @param  array<string, ReportField>  $fieldMap
     */
    private function assertColumnCardinality(
        AbstractDataset $dataset,
        User $user,
        int $companyId,
        array $cols,
        mixed $filters,
        array $fieldMap,
    ): void {
        $query = $dataset->newQuery();
        $query->from($dataset->table());
        $this->applyJoinsForDims($query, $dataset, $cols, $fieldMap);
        $this->applyScopeAndFilters($query, $dataset, $user, $filters, $fieldMap);

        // Distinct combo count for column dims
        $parts = [];
        foreach ($cols as $c) {
            $parts[] = $this->sqlExpr($fieldMap[$c['field']], $c['grain']);
        }
        $concat = 'COUNT(DISTINCT ('.implode(' || \'|\' || ', array_map(
            fn ($p) => "COALESCE({$p}::text, '')",
            $parts
        )).')) as c';
        $count = (int) ($query->toBase()->selectRaw($concat)->value('c') ?? 0);
        if ($count > self::MAX_COLUMN_CARDINALITY) {
            throw new InvalidArgumentException(
                'Bu alan sütun olarak çok fazla değer üretiyor ('.$count.' > '.self::MAX_COLUMN_CARDINALITY.')'
            );
        }
    }

    /**
     * @param  list<array{field: string, grain: ?string}>  $dims
     * @param  list<array{alias: string, format: string, decimals: int, sql: string, bindings: list<mixed>}>  $measures
     * @param  mixed  $filters
     * @param  array<string, ReportField>  $fieldMap
     * @return list<array<string, mixed>>
     */
    private function runGrouped(
        AbstractDataset $dataset,
        User $user,
        int $companyId,
        array $dims,
        array $measures,
        mixed $filters,
        array $fieldMap,
    ): array {
        $query = $dataset->newQuery();
        $query->from($dataset->table());

        // Joins for dim columns
        $this->applyJoinsForDims($query, $dataset, $dims, $fieldMap);
        $this->applyScopeAndFilters($query, $dataset, $user, $filters, $fieldMap);

        $selects = [];
        $groupExprs = [];
        $bindings = [];

        foreach ($dims as $d) {
            $alias = $d['field'].($d['grain'] ? '_'.$d['grain'] : '');
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
                throw new InvalidArgumentException('Geçersiz dim alias');
            }
            $expr = $this->sqlExpr($fieldMap[$d['field']], $d['grain']);
            $selects[] = DB::raw("{$expr} as \"{$alias}\"");
            $groupExprs[] = $expr;
        }

        foreach ($measures as $m) {
            $selects[] = DB::raw("{$m['sql']} as \"{$m['alias']}\"");
            foreach ($m['bindings'] as $b) {
                $bindings[] = $b;
            }
        }

        $query->select($selects);
        if ($groupExprs !== []) {
            $query->groupByRaw(implode(', ', $groupExprs));
        }

        // Apply measure bindings via whereRaw noop if needed — Laravel selectRaw with bindings:
        // DB::raw doesn't bind; re-select with selectRaw for measures that have bindings.
        if ($bindings !== []) {
            // Rebuild: Eloquent select with mixed bindings is tricky.
            // Use fromSub approach: build SQL manually with bindings on the base query.
            $base = $query->toBase();
            // Re-run with selectRaw combining everything
            $q2 = $dataset->newQuery();
            $q2->from($dataset->table());
            $this->applyJoinsForDims($q2, $dataset, $dims, $fieldMap);
            $this->applyScopeAndFilters($q2, $dataset, $user, $filters, $fieldMap);

            $selectSqlParts = [];
            $allBindings = [];
            foreach ($dims as $d) {
                $alias = $d['field'].($d['grain'] ? '_'.$d['grain'] : '');
                $expr = $this->sqlExpr($fieldMap[$d['field']], $d['grain']);
                $selectSqlParts[] = "{$expr} as \"{$alias}\"";
            }
            foreach ($measures as $m) {
                $selectSqlParts[] = "{$m['sql']} as \"{$m['alias']}\"";
                foreach ($m['bindings'] as $b) {
                    $allBindings[] = $b;
                }
            }
            $q2->selectRaw(implode(', ', $selectSqlParts), $allBindings);
            if ($groupExprs !== []) {
                $q2->groupByRaw(implode(', ', $groupExprs));
            }
            $q2->limit(ReportQueryBuilder::PREVIEW_MAX_ROWS);

            return $q2->toBase()->get()->map(fn ($r) => (array) $r)->all();
        }

        unset($base);
        $query->limit(ReportQueryBuilder::PREVIEW_MAX_ROWS);

        return $query->toBase()->get()->map(fn ($r) => (array) $r)->all();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<array{field: string, grain: ?string}>  $dims
     * @param  array<string, ReportField>  $fieldMap
     */
    private function applyJoinsForDims(Builder $query, AbstractDataset $dataset, array $dims, array $fieldMap): void
    {
        $allowed = [];
        foreach ($dataset->allowedJoins() as $j) {
            $allowed[$j['key']] = $j;
        }
        $needed = [];
        foreach ($dims as $d) {
            $col = $fieldMap[$d['field']]->column;
            if (str_starts_with($col, 'departments.') && isset($allowed['departments'])) {
                $needed['departments'] = $allowed['departments'];
            }
            if (str_starts_with($col, 'branches.') && isset($allowed['branches'])) {
                $needed['branches'] = $allowed['branches'];
            }
        }
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
     * @param  mixed  $filters
     * @param  array<string, ReportField>  $fieldMap
     */
    private function applyScopeAndFilters(Builder $query, AbstractDataset $dataset, User $user, mixed $filters, array $fieldMap): void
    {
        // DataScope via reflection of builder private — call through a thin public helper
        $this->builder->applyExternalScope($query, $dataset, $user);

        if (! is_array($filters)) {
            return;
        }
        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }
            $this->builder->applyExternalFilter($query, $filter, $fieldMap);
        }
    }

    private function sqlExpr(ReportField $field, ?string $grain): string
    {
        $base = $this->builder->externalSqlExpression($field);
        if ($grain === null) {
            return $base;
        }

        // Whitelist date_trunc / extract — kullanıcı ifadesi değil
        return match ($grain) {
            'year' => "EXTRACT(YEAR FROM {$base})::int",
            'quarter' => "EXTRACT(QUARTER FROM {$base})::int",
            'month' => "date_trunc('month', {$base})::date",
            'week' => "date_trunc('week', {$base})::date",
            'day' => "date_trunc('day', {$base})::date",
            default => throw new InvalidArgumentException('Geçersiz grain'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $flat
     * @param  list<array{field: string, grain: ?string}>  $dims
     * @return list<list<string|null>>
     */
    private function uniqueKeyCombos(array $flat, array $dims): array
    {
        if ($dims === []) {
            return [[]];
        }
        $seen = [];
        $out = [];
        foreach ($flat as $row) {
            $combo = [];
            foreach ($dims as $d) {
                $alias = $d['field'].($d['grain'] ? '_'.$d['grain'] : '');
                $v = $row[$alias] ?? null;
                $combo[] = $v === null || $v === '' ? null : (string) $v;
            }
            $k = $this->comboKey($combo);
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $combo;
            }
        }

        return $out;
    }

    /**
     * @param  list<string|null>  $combo
     */
    private function comboKey(array $combo): string
    {
        return implode("\x1e", array_map(fn ($v) => $v === null ? "\0" : $v, $combo));
    }

    /**
     * @param  list<string|null>  $combo
     * @return list<string>
     */
    private function labelCombo(array $combo): array
    {
        return array_map(fn ($v) => $v === null ? self::EMPTY_LABEL : $v, $combo);
    }

    /**
     * @param  list<array<string, mixed>>  $flat
     * @param  list<array{field: string, grain: ?string}>  $rows
     * @param  list<array{field: string, grain: ?string}>  $cols
     * @param  list<array{alias: string}>  $measures
     * @return array<string, array<string, mixed>>
     */
    private function indexFlat(array $flat, array $rows, array $cols, array $measures): array
    {
        $index = [];
        foreach ($flat as $row) {
            $rk = [];
            foreach ($rows as $d) {
                $alias = $d['field'].($d['grain'] ? '_'.$d['grain'] : '');
                $v = $row[$alias] ?? null;
                $rk[] = $v === null || $v === '' ? null : (string) $v;
            }
            $ck = [];
            foreach ($cols as $d) {
                $alias = $d['field'].($d['grain'] ? '_'.$d['grain'] : '');
                $v = $row[$alias] ?? null;
                $ck[] = $v === null || $v === '' ? null : (string) $v;
            }
            $key = $this->comboKey(array_merge($rk, $ck));
            $vals = [];
            foreach ($measures as $m) {
                $vals[$m['alias']] = $row[$m['alias']] ?? null;
            }
            $index[$key] = $vals;
        }

        return $index;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  list<list<string|null>>  $rowKeys
     * @param  list<list<string|null>>  $colKeys
     * @param  list<array{field: string, grain: ?string}>  $rows
     * @param  list<array{field: string, grain: ?string}>  $cols
     * @param  list<array{alias: string}>  $measures
     * @return list<array<string, mixed>>
     */
    private function computeSubtotals(array $index, array $rowKeys, array $colKeys, array $rows, array $cols, array $measures): array
    {
        // İlk satır boyutu seviyesinde topla (basit alt toplam)
        if ($rows === []) {
            return [];
        }
        $groups = [];
        foreach ($rowKeys as $rk) {
            $prefix = [$rk[0] ?? null];
            $pk = $this->comboKey($prefix);
            if (! isset($groups[$pk])) {
                $groups[$pk] = ['key' => $this->labelCombo($prefix), 'totals' => []];
            }
            foreach ($colKeys as $ck) {
                $full = $this->comboKey(array_merge($rk, $ck));
                foreach ($measures as $m) {
                    $a = $m['alias'];
                    $v = $index[$full][$a] ?? null;
                    if ($v === null) {
                        continue;
                    }
                    $ckey = $this->comboKey($ck);
                    $groups[$pk]['totals'][$ckey][$a] = ($groups[$pk]['totals'][$ckey][$a] ?? 0) + (float) $v;
                }
            }
        }

        return array_values($groups);
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  list<list<string|null>>  $rowKeys
     * @param  list<list<string|null>>  $colKeys
     * @param  list<array{alias: string}>  $measures
     * @return array<string, mixed>
     */
    private function computeGrand(array $index, array $rowKeys, array $colKeys, array $measures): array
    {
        $totals = [];
        foreach ($rowKeys as $rk) {
            foreach ($colKeys as $ck) {
                $full = $this->comboKey(array_merge($rk, $ck));
                foreach ($measures as $m) {
                    $a = $m['alias'];
                    $v = $index[$full][$a] ?? null;
                    if ($v === null) {
                        continue;
                    }
                    $totals[$a] = ($totals[$a] ?? 0) + (float) $v;
                }
            }
        }

        return $totals;
    }

    /**
     * @param  mixed  $cellFilters
     * @param  array<string, ReportField>  $fieldMap
     * @return list<array{field: string, op: string, value?: mixed}>
     */
    private function normalizeCellFilters(mixed $cellFilters, array $fieldMap): array
    {
        if (! is_array($cellFilters)) {
            return [];
        }
        $out = [];
        foreach ($cellFilters as $f) {
            if (! is_array($f) || ! is_string($f['field'] ?? null)) {
                continue;
            }
            $key = $f['field'];
            if (! isset($fieldMap[$key])) {
                throw new InvalidArgumentException('Drill filtresi yetkisiz: '.$key);
            }
            $value = $f['value'] ?? null;
            if ($value === null || $value === self::EMPTY_LABEL) {
                $out[] = ['field' => $key, 'op' => 'is_null'];
            } else {
                $out[] = ['field' => $key, 'op' => 'eq', 'value' => $value];
            }
        }

        return $out;
    }
}
