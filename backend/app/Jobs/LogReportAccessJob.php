<?php

namespace App\Jobs;

use App\Models\ReportAccessLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * D1e — Rapor erişim logu (senkron yolu bozmamak için queue / afterResponse).
 */
class LogReportAccessJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{
     *   company_id: int,
     *   user_id: int,
     *   report_id?: int|null,
     *   dashboard_id?: int|null,
     *   action: string,
     *   dataset_key?: string|null,
     *   row_count?: int,
     *   contains_sensitive?: bool,
     *   sensitive_fields?: list<string>|null,
     *   filters_hash?: string|null,
     *   filter_field_keys?: list<string>|null,
     *   duration_ms?: int|null,
     *   ip?: string|null,
     *   user_agent?: string|null,
     * }  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        try {
            ReportAccessLog::create([
                'company_id' => (int) $this->payload['company_id'],
                'user_id' => (int) $this->payload['user_id'],
                'report_id' => $this->payload['report_id'] ?? null,
                'dashboard_id' => $this->payload['dashboard_id'] ?? null,
                'action' => (string) $this->payload['action'],
                'dataset_key' => $this->payload['dataset_key'] ?? null,
                'row_count' => (int) ($this->payload['row_count'] ?? 0),
                'contains_sensitive' => (bool) ($this->payload['contains_sensitive'] ?? false),
                'sensitive_fields' => $this->payload['sensitive_fields'] ?? null,
                'filters_hash' => $this->payload['filters_hash'] ?? null,
                'filter_field_keys' => $this->payload['filter_field_keys'] ?? null,
                'duration_ms' => $this->payload['duration_ms'] ?? null,
                'ip' => $this->payload['ip'] ?? null,
                'user_agent' => $this->payload['user_agent'] ?? null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('report_access_log_failed', ['error' => $e->getMessage()]);
        }
    }
}
