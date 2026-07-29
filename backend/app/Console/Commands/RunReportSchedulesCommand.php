<?php

namespace App\Console\Commands;

use App\Services\Reports\ReportScheduleService;
use Illuminate\Console\Command;

/**
 * D1f — Vadesi gelen zamanlanmış raporları kuyruğa alır.
 */
class RunReportSchedulesCommand extends Command
{
    protected $signature = 'reports:run-schedules';

    protected $description = 'Vadesi gelen report_schedules kayıtlarını kuyruğa alır';

    public function handle(ReportScheduleService $schedules): int
    {
        $count = $schedules->dispatchDue();
        $this->info("Kuyruğa alınan zamanlama: {$count}");

        return self::SUCCESS;
    }
}
