<?php

namespace App\Jobs;

use App\Exceptions\CompanyContextMissingException;
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
 * D1f / G2 — Ağır export (30sn+ riski) kuyrukta; hazır olunca bildirim.
 * reportScope=group iken companyIds ctor ile taşınır (home company_id ile çözülmez).
 */
class ProcessHeavyReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  list<int>|null  $companyIds
     */
    public function __construct(
        public int $reportId,
        public int $userId,
        public int $companyId,
        public string $reportScope = 'company',
        public ?array $companyIds = null,
    ) {}

    public function handle(
        ReportDefinitionService $reports,
        NotificationService $notifications,
    ): void {
        if ($this->companyId < 1) {
            throw new CompanyContextMissingException(
                'ProcessHeavyReportExportJob requires a valid company context (companyId).'
            );
        }

        $report = SavedReport::query()->find($this->reportId);
        $user = User::query()->find($this->userId);
        if (! $report || ! $user) {
            return;
        }

        CompanyContext::run($this->companyId, function () use ($reports, $notifications, $report, $user): void {
            try {
                $overrides = [
                    'scope' => in_array($this->reportScope, ['company', 'group'], true)
                        ? $this->reportScope
                        : 'company',
                ];
                if ($overrides['scope'] === 'group' && is_array($this->companyIds) && $this->companyIds !== []) {
                    $overrides['__company_ids'] = array_values(array_map('intval', $this->companyIds));
                }

                $result = $reports->exportSaved($report, $user, $this->companyId, $overrides);
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
                    'report_scope' => $this->reportScope,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }
}
