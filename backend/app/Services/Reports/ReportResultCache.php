<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * D1f — Rapor/dashboard sonuç cache'i.
 *
 * Anahtar: hash(report_id|config, kapsam_imzası, filtreler, sayfa/sıralama).
 * Kaynak tablo yazımında genel temizleme YOK — TTL yeter (DUR).
 */
final class ReportResultCache
{
    public const DEFAULT_TTL_SECONDS = 300;

    public const SLOW_QUERY_SECONDS = 5;

    public const STATEMENT_TIMEOUT_MS = 30000;

    public function __construct(
        protected ReportScopeSignature $signatures,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function key(
        User $user,
        int $companyId,
        array $config,
        ?int $reportId = null,
        ?int $dashboardId = null,
        ?string $widgetId = null,
    ): string {
        $scopeSig = $this->signatures->build($user, $companyId, $config);
        $payload = [
            'report_id' => $reportId,
            'dashboard_id' => $dashboardId,
            'widget_id' => $widgetId,
            'config' => $this->normalizeConfigForKey($config),
            'scope_sig' => $scopeSig,
            'global_ver' => $this->globalVersion(),
        ];

        return 'report_result:'.hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function bumpGlobal(): void
    {
        Cache::forever('report_cache_global_ver', (string) microtime(true));
    }

    public function globalVersion(): string
    {
        return (string) Cache::get('report_cache_global_ver', '0');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}|null
     */
    public function get(string $key): ?array
    {
        $cached = Cache::get($key);
        if (! is_array($cached) || ! isset($cached['rows'], $cached['meta'])) {
            return null;
        }

        return $cached;
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, meta: array<string, mixed>}  $result
     */
    public function put(string $key, array $result, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }
        Cache::put($key, $result, $ttlSeconds);
    }

    public function forgetReport(int $reportId): void
    {
        // Tag yoksa prefix scan pahalı — rapor config değişiminde
        // yeni anahtarlar zaten farklı; eski TTL ile düşer.
        // Explicit forget için version bump tercih edilir.
        Cache::forever($this->reportVersionKey($reportId), (string) microtime(true));
    }

    public function forgetDashboard(int $dashboardId): void
    {
        Cache::forever($this->dashboardVersionKey($dashboardId), (string) microtime(true));
    }

    public function reportVersion(int $reportId): string
    {
        return (string) Cache::get($this->reportVersionKey($reportId), '0');
    }

    public function dashboardVersion(int $dashboardId): string
    {
        return (string) Cache::get($this->dashboardVersionKey($dashboardId), '0');
    }

    public function resolveTtl(?int $reportTtl): int
    {
        if ($reportTtl === null) {
            return self::DEFAULT_TTL_SECONDS;
        }

        return max(0, $reportTtl);
    }

    public function logSlowQuery(string $dataset, float $seconds, int $rowCount): void
    {
        if ($seconds < self::SLOW_QUERY_SECONDS) {
            return;
        }
        Log::channel('single')->warning('report.slow_query', [
            'dataset' => $dataset,
            'duration_sec' => round($seconds, 3),
            'row_count' => $rowCount,
        ]);
    }

    private function reportVersionKey(int $reportId): string
    {
        return "report_cache_ver:{$reportId}";
    }

    private function dashboardVersionKey(int $dashboardId): string
    {
        return "dashboard_cache_ver:{$dashboardId}";
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfigForKey(array $config): array
    {
        unset($config['__export'], $config['__bypass_cache'], $config['__schedule_id']);
        ksort($config);

        return $config;
    }
}
