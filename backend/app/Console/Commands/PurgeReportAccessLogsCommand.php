<?php

namespace App\Console\Commands;

use App\Models\ReportAccessLog;
use Illuminate\Console\Command;

/**
 * D1e — Eski rapor erişim loglarını temizle (varsayılan 12 ay).
 */
class PurgeReportAccessLogsCommand extends Command
{
    protected $signature = 'reports:purge-access-logs {--months=12 : Saklama süresi (ay)}';

    protected $description = 'Eski report_access_logs kayıtlarını siler (append-only saklama politikası)';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months);
        // Append-only model deleting event engeller — force delete via query builder
        $count = ReportAccessLog::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->toBase()
            ->delete();

        $this->info("Silinen kayıt: {$count} (cutoff {$cutoff->toDateString()}, {$months} ay)");

        return self::SUCCESS;
    }
}
