<?php

namespace App\Jobs;

use App\Models\ReportSchedule;
use App\Services\Reports\ReportScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * D1f — Zamanlanmış rapor çalıştırma (her alıcı kendi kapsamında).
 */
class RunReportScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $scheduleId,
    ) {}

    public function handle(ReportScheduleService $schedules): void
    {
        $schedule = ReportSchedule::query()->find($this->scheduleId);
        if (! $schedule || ! $schedule->active) {
            return;
        }

        \App\Support\CompanyContext::run((int) $schedule->company_id, function () use ($schedules, $schedule): void {
            try {
                $schedules->runSchedule($schedule);
            } catch (\Throwable $e) {
                Log::error('report.schedule.job_failed', [
                    'schedule_id' => $this->scheduleId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }
}
