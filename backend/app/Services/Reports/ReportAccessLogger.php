<?php

namespace App\Services\Reports;

use App\Jobs\LogReportAccessJob;

/**
 * D1e — Rapor erişim logu yazıcı (filtre değerleri ham yazılmaz).
 */
final class ReportAccessLogger
{
    /**
     * @param  array{
     *   company_id: int,
     *   user_id: int,
     *   action: string,
     *   report_id?: int|null,
     *   dashboard_id?: int|null,
     *   dataset_key?: string|null,
     *   row_count?: int,
     *   contains_sensitive?: bool,
     *   sensitive_fields?: list<string>,
     *   filters?: list<array<string, mixed>>|null,
     *   duration_ms?: int|null,
     *   ip?: string|null,
     *   user_agent?: string|null,
     * }  $data
     */
    public static function record(array $data): void
    {
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $fieldKeys = [];
        foreach ($filters as $f) {
            if (is_array($f) && isset($f['field']) && is_string($f['field'])) {
                $fieldKeys[] = $f['field'];
            }
        }
        $fieldKeys = array_values(array_unique($fieldKeys));
        // Ham değer yok — yalnız alan adları + hash
        $hashPayload = array_map(function ($f) {
            if (! is_array($f)) {
                return [];
            }

            return [
                'field' => $f['field'] ?? null,
                'op' => $f['op'] ?? null,
                // value bilerek dahil edilmez
            ];
        }, $filters);

        $payload = [
            'company_id' => (int) $data['company_id'],
            'user_id' => (int) $data['user_id'],
            'report_id' => $data['report_id'] ?? null,
            'dashboard_id' => $data['dashboard_id'] ?? null,
            'action' => (string) $data['action'],
            'dataset_key' => $data['dataset_key'] ?? null,
            'row_count' => (int) ($data['row_count'] ?? 0),
            'contains_sensitive' => (bool) ($data['contains_sensitive'] ?? false),
            'sensitive_fields' => $data['sensitive_fields'] ?? [],
            'filters_hash' => $filters === [] ? null : hash('sha256', json_encode($hashPayload)),
            'filter_field_keys' => $fieldKeys,
            'duration_ms' => $data['duration_ms'] ?? null,
            'ip' => $data['ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ];

        $job = new LogReportAccessJob($payload);
        if (app()->environment('testing')) {
            $job->handle();

            return;
        }
        try {
            dispatch($job)->afterResponse();
        } catch (\Throwable) {
            $job->handle();
        }
    }

    /**
     * @param  list<ReportField>  $fields
     * @return list<string>
     */
    public static function sensitiveKeys(array $fields): array
    {
        $keys = [];
        foreach ($fields as $f) {
            if ($f->isClassifiedSensitive()) {
                $keys[] = $f->key;
            }
        }

        return $keys;
    }
}
