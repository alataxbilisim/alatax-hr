<?php

namespace App\Jobs;

use App\Models\SavedReport;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Reports\ReportDefinitionService;
use App\Support\CompanyContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * D1f — Ağır export (30sn+ riski) kuyrukta; hazır olunca bildirim.
 */
class ProcessHeavyReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $reportId,
        public int $userId,
        public int $companyId,
    ) {}

    public function handle(
        ReportDefinitionService $reports,
        NotificationService $notifications,
    ): void {
        $report = SavedReport::query()->find($this->reportId);
        $user = User::query()->find($this->userId);
        if (! $report || ! $user) {
            return;
        }

        CompanyContext::run($this->companyId, function () use ($reports, $notifications, $report, $user): void {
            try {
                $result = $reports->exportSaved($report, $user, $this->companyId);
                $path = 'report-exports/'.$this->companyId.'/'.$this->reportId.'_'.$this->userId.'_'.time().'.json';
                Storage::disk('local')->put($path, json_encode([
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ]));

                $notifications->notify($user, 'reports.export.ready', [
                    'company_id' => $this->companyId,
                    'title' => $report->name,
                    'entity' => $report->name,
                    'path' => '/reports/'.$report->id,
                    'panel' => 'company',
                ]);
            } catch (\Throwable $e) {
                Log::error('report.export.job_failed', [
                    'report_id' => $this->reportId,
                    'user_id' => $this->userId,
                    'company_id' => $this->companyId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }
}
