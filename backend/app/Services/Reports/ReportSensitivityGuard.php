<?php

namespace App\Services\Reports;

/**
 * D1e — Minimum hücre maskeleme (post-process; SQL motoruna dokunmaz).
 *
 * Alt/genel toplam kararı: herhangi bir yaprak hücre maskelendiğinde ilgili
 * alt toplam ve genel toplam da maskelenir. Böylece fark alınarak küçük grup
 * değerleri geri hesaplanamaz.
 */
final class ReportSensitivityGuard
{
    /**
     * @param  list<array<string, mixed>>  $flatRows  GROUP BY satırları (_distinct_persons dahil olabilir)
     * @param  list<string>  $measureAliases
     * @return list<array<string, mixed>>
     */
    public static function maskAggregateRows(array $flatRows, array $measureAliases, int $threshold, bool $enabled): array
    {
        if (! $enabled) {
            return $flatRows;
        }
        $out = [];
        foreach ($flatRows as $row) {
            $persons = isset($row['_distinct_persons']) ? (int) $row['_distinct_persons'] : null;
            if ($persons !== null && $persons > 0 && $persons < $threshold) {
                foreach ($measureAliases as $alias) {
                    if (array_key_exists($alias, $row)) {
                        $row[$alias] = ReportPrivacySettings::MASKED_VALUE;
                    }
                }
                $row['_masked'] = true;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Pivot hücrelerini maskele; maskeli yaprak varsa grand/subtotal da maskelenir.
     *
     * @param  list<array{row: int, col: int, measure: string, value: mixed}>  $cells
     * @param  array<string, array<string, mixed>>  $index  comboKey => measures + _distinct_persons
     * @param  list<list<mixed>>  $rowKeys
     * @param  list<list<mixed>>  $colKeys
     * @param  list<array{field: string, grain: ?string}>  $rows
     * @param  list<array{field: string, grain: ?string}>  $cols
     * @param  callable(list<mixed>): string  $comboKeyFn
     * @return array{cells: list<array<string, mixed>>, any_masked: bool}
     */
    public static function maskPivotCells(
        array $cells,
        array $index,
        array $rowKeys,
        array $colKeys,
        array $rows,
        array $cols,
        callable $comboKeyFn,
        int $threshold,
        bool $enabled,
    ): array {
        if (! $enabled) {
            return ['cells' => $cells, 'any_masked' => false];
        }

        $anyMasked = false;
        $maskedLeaf = [];
        foreach ($rowKeys as $ri => $rk) {
            foreach ($colKeys as $ci => $ck) {
                $key = $comboKeyFn(array_merge($rk, $ck));
                $persons = isset($index[$key]['_distinct_persons'])
                    ? (int) $index[$key]['_distinct_persons']
                    : null;
                if ($persons !== null && $persons > 0 && $persons < $threshold) {
                    $maskedLeaf["{$ri}|{$ci}"] = true;
                    $anyMasked = true;
                }
            }
        }

        $out = [];
        foreach ($cells as $cell) {
            $leafKey = $cell['row'].'|'.$cell['col'];
            if (isset($maskedLeaf[$leafKey])) {
                $cell['value'] = ReportPrivacySettings::MASKED_VALUE;
                $cell['masked'] = true;
            }
            $out[] = $cell;
        }

        return ['cells' => $out, 'any_masked' => $anyMasked];
    }

    /**
     * @param  list<ReportField>  $involvedFields
     */
    public static function needsGuard(array $involvedFields): bool
    {
        foreach ($involvedFields as $f) {
            if ($f->requiresMinCellGuard()) {
                return true;
            }
        }

        return false;
    }
}
