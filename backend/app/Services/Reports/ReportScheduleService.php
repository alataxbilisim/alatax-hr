<?php

namespace App\Services\Reports;

use App\Jobs\RunReportScheduleJob;
use App\Models\Dashboard;
use App\Models\ReportSchedule;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * D1f — Zamanlanmış rapor CRUD + due dispatch + çalıştırma orkestrasyonu.
 *
 * Her alıcı kendi DataScope / alan izinleriyle üretilir; sahip sonucu kopyalanmaz.
 */
class ReportScheduleService
{
    public const MAX_FAILURES = 3;

    public function __construct(
        protected ReportScheduleRecipientResolver $recipients,
        protected ReportAttachmentGuard $attachments,
        protected ReportDefinitionService $reports,
        protected NotificationService $notifications,
        protected DatasetRegistry $registry,
    ) {}

    public function listFor(User $user, int $companyId, int $perPage = 20): LengthAwarePaginator
    {
        $query = ReportSchedule::query()->where('company_id', $companyId);
        if ($user->type?->value !== 'company_admin') {
            $query->where('owner_id', $user->id);
        }

        return $query
            ->with(['report:id,name,dataset_key', 'dashboard:id,name', 'owner:id,name,email'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, int $companyId, array $data): ReportSchedule
    {
        $this->assertTarget($companyId, $data);
        $recipients = is_array($data['recipients'] ?? null) ? $data['recipients'] : [];
        $this->recipients->assertWithinLimit($companyId, $recipients);

        $format = (string) ($data['format'] ?? 'link');
        if (isset($data['report_id'])) {
            $report = SavedReport::query()->where('company_id', $companyId)->findOrFail((int) $data['report_id']);
            if (! $report->isAccessibleBy($user)) {
                abort(403, 'Bu rapora erişim yok');
            }
            if (in_array($format, ['excel', 'pdf'], true) && $this->attachments->blocksAttachment($report)) {
                throw ValidationException::withMessages([
                    'format' => ['Bu rapor özel/anonim kaynak alan içerdiği için ek gönderimi kapalıdır; format=link kullanın.'],
                ]);
            }
        }

        $schedule = ReportSchedule::create([
            'company_id' => $companyId,
            'report_id' => $data['report_id'] ?? null,
            'dashboard_id' => $data['dashboard_id'] ?? null,
            'owner_id' => $user->id,
            'name' => $data['name'],
            'cadence' => $data['cadence'],
            'hour' => (int) ($data['hour'] ?? 8),
            'minute' => (int) ($data['minute'] ?? 0),
            'day' => $data['day'] ?? null,
            'cron_expression' => $data['cron_expression'] ?? null,
            'timezone' => $data['timezone'] ?? 'Europe/Istanbul',
            'format' => $format,
            'recipients' => $recipients,
            'filters' => $data['filters'] ?? null,
            'only_if_data' => (bool) ($data['only_if_data'] ?? false),
            'active' => (bool) ($data['active'] ?? true),
            'next_run_at' => $this->computeNextRun($data),
        ]);

        return $schedule->fresh(['report', 'dashboard', 'owner']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ReportSchedule $schedule, User $user, array $data): ReportSchedule
    {
        if ((int) $schedule->owner_id !== (int) $user->id && $user->type?->value !== 'company_admin') {
            abort(403, 'Bu zamanlamayı düzenleme yetkiniz yok');
        }

        if (array_key_exists('recipients', $data)) {
            $recipients = is_array($data['recipients']) ? $data['recipients'] : [];
            $this->recipients->assertWithinLimit((int) $schedule->company_id, $recipients);
            $schedule->recipients = $recipients;
        }

        foreach (['name', 'cadence', 'timezone', 'cron_expression', 'format'] as $k) {
            if (array_key_exists($k, $data)) {
                $schedule->{$k} = $data[$k];
            }
        }
        foreach (['hour', 'minute', 'day'] as $k) {
            if (array_key_exists($k, $data)) {
                $schedule->{$k} = $data[$k];
            }
        }
        foreach (['only_if_data', 'active'] as $k) {
            if (array_key_exists($k, $data)) {
                $schedule->{$k} = (bool) $data[$k];
            }
        }
        if (array_key_exists('filters', $data)) {
            $schedule->filters = $data['filters'];
        }

        if ($schedule->report_id && in_array((string) $schedule->format, ['excel', 'pdf'], true)) {
            $report = $schedule->report ?? SavedReport::find($schedule->report_id);
            if ($report && $this->attachments->blocksAttachment($report)) {
                throw ValidationException::withMessages([
                    'format' => ['Bu rapor özel/anonim kaynak alan içerdiği için ek gönderimi kapalıdır; format=link kullanın.'],
                ]);
            }
        }

        $schedule->next_run_at = $this->computeNextRun($schedule->toArray());
        $schedule->save();

        return $schedule->fresh(['report', 'dashboard', 'owner']);
    }

    public function delete(ReportSchedule $schedule, User $user): void
    {
        if ((int) $schedule->owner_id !== (int) $user->id && $user->type?->value !== 'company_admin') {
            abort(403, 'Bu zamanlamayı silme yetkiniz yok');
        }
        $schedule->delete();
    }

    /**
     * Due olan aktif zamanlamaları kuyruğa alır.
     */
    public function dispatchDue(): int
    {
        $due = ReportSchedule::query()
            ->where('active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->limit(100)
            ->get();

        foreach ($due as $schedule) {
            // Çift dispatch önle: next_run_at ilerlet
            $schedule->next_run_at = $this->computeNextRun($schedule->toArray(), Carbon::now($schedule->timezone)->addMinute());
            $schedule->save();
            RunReportScheduleJob::dispatch($schedule->id);
        }

        return $due->count();
    }

    /**
     * Tek zamanlamayı çalıştır — her alıcı kendi kapsamında.
     *
     * @return array{status: string, delivered: int, skipped: int, failed: int, logs: list<array<string, mixed>>}
     */
    public function runSchedule(ReportSchedule $schedule): array
    {
        $logs = [];
        $delivered = 0;
        $skipped = 0;
        $failed = 0;

        try {
            if (! $schedule->report_id) {
                // Dashboard zamanlama: her alıcıya link bildirimi (batch run UI'da)
                return $this->runDashboardSchedule($schedule);
            }

            $report = SavedReport::query()->find($schedule->report_id);
            if (! $report) {
                throw new \RuntimeException('Rapor bulunamadı');
            }

            $users = $this->recipients->resolve((int) $schedule->company_id, is_array($schedule->recipients) ? $schedule->recipients : []);
            $format = $this->attachments->effectiveFormat($report, (string) $schedule->format);
            $attachmentBlocked = $format === 'link' && in_array((string) $schedule->format, ['excel', 'pdf'], true);

            foreach ($users as $recipient) {
                $recipient = $recipient->loadMissing(['roles', 'employee']);
                if (! $report->isAccessibleBy($recipient)) {
                    $skipped++;
                    $logs[] = [
                        'user_id' => $recipient->id,
                        'status' => 'skipped_no_access',
                    ];

                    continue;
                }

                try {
                    $overrides = is_array($schedule->filters) ? ['filters' => $schedule->filters] : [];
                    $overrides['__schedule_id'] = $schedule->id;
                    $result = $this->reports->run($report, $recipient, (int) $schedule->company_id, $overrides);

                    // Access log: scheduled
                    ReportAccessLogger::record([
                        'company_id' => (int) $schedule->company_id,
                        'user_id' => (int) $recipient->id,
                        'report_id' => (int) $report->id,
                        'action' => 'scheduled',
                        'dataset_key' => $report->dataset_key,
                        'row_count' => (int) ($result['meta']['count'] ?? count($result['rows'])),
                        'contains_sensitive' => ! empty($result['meta']['hidden_fields']),
                        'sensitive_fields' => [],
                        'filters' => is_array($schedule->filters) ? $schedule->filters : [],
                        'duration_ms' => null,
                    ]);

                    $rowCount = (int) ($result['meta']['count'] ?? count($result['rows']));
                    if ($schedule->only_if_data && $rowCount === 0) {
                        $skipped++;
                        $logs[] = ['user_id' => $recipient->id, 'status' => 'skipped_no_data', 'rows' => 0];

                        continue;
                    }

                    $this->deliverToRecipient($schedule, $report, $recipient, $format, $attachmentBlocked, $rowCount);
                    $delivered++;
                    $logs[] = [
                        'user_id' => $recipient->id,
                        'status' => 'delivered',
                        'rows' => $rowCount,
                        'format' => $format,
                    ];
                } catch (\Throwable $e) {
                    $failed++;
                    $logs[] = [
                        'user_id' => $recipient->id,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                    Log::warning('report.schedule.recipient_failed', [
                        'schedule_id' => $schedule->id,
                        'user_id' => $recipient->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $status = $failed > 0 && $delivered === 0 ? 'failed' : ($failed > 0 ? 'partial' : 'success');
            $this->finalizeRun($schedule, $status, $failed > 0 && $delivered === 0);

            return compact('status', 'delivered', 'skipped', 'failed', 'logs');
        } catch (\Throwable $e) {
            $this->finalizeRun($schedule, 'failed', true);
            throw $e;
        }
    }

    /**
     * @return array{status: string, delivered: int, skipped: int, failed: int, logs: list<array<string, mixed>>}
     */
    private function runDashboardSchedule(ReportSchedule $schedule): array
    {
        $dashboard = Dashboard::query()->find($schedule->dashboard_id);
        if (! $dashboard) {
            throw new \RuntimeException('Pano bulunamadı');
        }
        $users = $this->recipients->resolve((int) $schedule->company_id, is_array($schedule->recipients) ? $schedule->recipients : []);
        $delivered = 0;
        $skipped = 0;
        $logs = [];
        foreach ($users as $recipient) {
            if (! $dashboard->isAccessibleBy($recipient)) {
                $skipped++;
                $logs[] = ['user_id' => $recipient->id, 'status' => 'skipped_no_access'];

                continue;
            }
            $this->notifications->notify($recipient, 'reports.scheduled.ready', [
                'company_id' => (int) $schedule->company_id,
                'title' => $dashboard->name,
                'entity' => $dashboard->name,
                'path' => '/dashboards/'.$dashboard->id,
                'panel' => 'company',
            ]);
            $delivered++;
            $logs[] = ['user_id' => $recipient->id, 'status' => 'delivered', 'format' => 'link'];
        }
        $this->finalizeRun($schedule, 'success', false);

        return [
            'status' => 'success',
            'delivered' => $delivered,
            'skipped' => $skipped,
            'failed' => 0,
            'logs' => $logs,
        ];
    }

    private function deliverToRecipient(
        ReportSchedule $schedule,
        SavedReport $report,
        User $recipient,
        string $format,
        bool $attachmentBlocked,
        int $rowCount,
    ): void {
        $bodyExtra = '';
        if ($attachmentBlocked) {
            $bodyExtra = ' (ek gönderimi hassasiyet nedeniyle kapatıldı)';
        } elseif (in_array($format, ['excel', 'pdf'], true)) {
            $bodyExtra = ' UYARI: E-posta eki kontrolsüz dağıtım riski taşır; paylaşımı sınırlı tutun.';
        }

        // Varsayılan: link — veri e-postada yok
        $this->notifications->notify($recipient, 'reports.scheduled.ready', [
            'company_id' => (int) $schedule->company_id,
            'title' => $report->name.$bodyExtra,
            'entity' => $report->name,
            'date' => now()->toDateString(),
            'path' => '/reports/'.$report->id,
            'panel' => 'company',
            'rows' => (string) $rowCount,
        ]);

        // excel/pdf ek: D1f'te Storage + ayrı Mailable genişletmesi — şimdilik link zorunlu
        // (format excel/pdf seçilse bile KVKK varsayılanı link; gerçek ek dosya üretimi sonraki ince ayar)
        if (in_array($format, ['excel', 'pdf'], true) && ! $this->attachments->blocksAttachment($report)) {
            // Ek üretimi pahalı — queued export + in-app "hazır" yeterli; e-posta gövdesinde uyarı yukarıda.
            // Dosya eki bilerek gönderilmiyor (anonim erişim yok; Storage private).
        }
    }

    private function finalizeRun(ReportSchedule $schedule, string $status, bool $countFailure): void
    {
        $schedule->last_run_at = now();
        $schedule->last_status = $status;
        if ($countFailure) {
            $schedule->failure_count = (int) $schedule->failure_count + 1;
            if ($schedule->failure_count >= self::MAX_FAILURES) {
                $schedule->active = false;
                $owner = $schedule->owner;
                if ($owner) {
                    try {
                        $this->notifications->notify($owner, 'reports.scheduled.disabled', [
                            'company_id' => (int) $schedule->company_id,
                            'title' => $schedule->name,
                            'entity' => $schedule->name,
                            'path' => '/reports/schedules',
                            'panel' => 'company',
                        ]);
                    } catch (\Throwable) {
                        // bildirim katalog eksikse sessiz
                    }
                }
            }
        } else {
            $schedule->failure_count = 0;
        }
        if ($schedule->next_run_at === null || $schedule->next_run_at->isPast()) {
            $schedule->next_run_at = $this->computeNextRun($schedule->toArray());
        }
        $schedule->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTarget(int $companyId, array $data): void
    {
        $hasReport = ! empty($data['report_id']);
        $hasDash = ! empty($data['dashboard_id']);
        if ($hasReport === $hasDash) {
            throw ValidationException::withMessages([
                'report_id' => ['Tam olarak biri zorunlu: report_id veya dashboard_id'],
            ]);
        }
        if ($hasReport) {
            $exists = SavedReport::query()->where('company_id', $companyId)->whereKey((int) $data['report_id'])->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['report_id' => ['Rapor bulunamadı']]);
            }
        }
        if ($hasDash) {
            $exists = Dashboard::query()->where('company_id', $companyId)->whereKey((int) $data['dashboard_id'])->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['dashboard_id' => ['Pano bulunamadı']]);
            }
        }
        if (! in_array((string) ($data['cadence'] ?? ''), ReportSchedule::CADENCES, true)) {
            throw ValidationException::withMessages(['cadence' => ['Geçersiz cadence']]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function computeNextRun(array $data, ?Carbon $after = null): Carbon
    {
        $tz = (string) ($data['timezone'] ?? 'Europe/Istanbul');
        $hour = (int) ($data['hour'] ?? 8);
        $minute = (int) ($data['minute'] ?? 0);
        $cadence = (string) ($data['cadence'] ?? 'daily');
        $base = ($after ?? Carbon::now($tz))->copy()->timezone($tz);

        if ($cadence === 'cron' && ! empty($data['cron_expression'])) {
            // Basit: bir saat sonra (cron parser bağımlılığı yok — DUR notu)
            return $base->copy()->addHour()->second(0);
        }

        $candidate = $base->copy()->setTime($hour, $minute, 0);
        if ($candidate->lessThanOrEqualTo($base)) {
            $candidate->addDay();
        }

        if ($cadence === 'weekly') {
            $day = (int) ($data['day'] ?? 1); // 1=Mon .. 7=Sun (Carbon iso)
            $day = max(1, min(7, $day));
            while ((int) $candidate->dayOfWeekIso !== $day) {
                $candidate->addDay();
            }
        } elseif ($cadence === 'monthly') {
            $dom = (int) ($data['day'] ?? 1);
            $dom = max(1, min(28, $dom));
            $candidate->day(min($dom, $candidate->daysInMonth));
            if ($candidate->lessThanOrEqualTo($base)) {
                $candidate->addMonthNoOverflow()->day(min($dom, $candidate->daysInMonth));
            }
        }

        return $candidate->utc();
    }
}
