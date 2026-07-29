<?php

namespace App\Services\Reports;

use App\Models\SavedReport;

/**
 * D1f — Ek gönderimi (excel/pdf) için hassasiyet guard.
 * special / anonymous_source alan içeren raporda ek TAMAMEN KAPALI.
 */
final class ReportAttachmentGuard
{
    public function __construct(
        protected DatasetRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function blocksAttachment(SavedReport $report, ?array $config = null): bool
    {
        $cfg = $config ?? (is_array($report->config) ? $report->config : []);
        $datasetKey = (string) ($report->dataset_key ?? ($cfg['dataset'] ?? ''));
        if ($datasetKey === '' || ! $this->registry->has($datasetKey)) {
            return false;
        }
        $fields = $this->registry->get($datasetKey)->fieldsForCompany((int) $report->company_id);
        $map = [];
        foreach ($fields as $f) {
            $map[$f->key] = $f;
        }

        $requested = $cfg['fields'] ?? array_keys($map);
        if (! is_array($requested)) {
            $requested = array_keys($map);
        }
        foreach ($requested as $key) {
            if (! is_string($key) || ! isset($map[$key])) {
                continue;
            }
            if ($map[$key]->requiresMinCellGuard()) {
                return true;
            }
        }

        // aggregations / measures
        $aggs = $cfg['aggregations'] ?? [];
        if (is_array($aggs)) {
            foreach ($aggs as $agg) {
                if (! is_array($agg)) {
                    continue;
                }
                $fk = $agg['field'] ?? null;
                if (is_string($fk) && isset($map[$fk]) && $map[$fk]->requiresMinCellGuard()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function effectiveFormat(SavedReport $report, string $requestedFormat): string
    {
        if (in_array($requestedFormat, ['excel', 'pdf'], true) && $this->blocksAttachment($report)) {
            return 'link';
        }

        return in_array($requestedFormat, ['link', 'excel', 'pdf'], true) ? $requestedFormat : 'link';
    }
}
