<?php

namespace App\Services\Notification;

use App\Mail\NotificationMail;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\Asset;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\OnboardingTask;
use App\Models\SalaryReviewPeriod;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Bildirim merkezi — tek giriş noktası (4C).
 *
 * Kanal: in-app + e-posta (tercih). Güvenlik olayları tercihle kapatılamaz.
 * Şablon: company override → sistem varsayılanı; XSS escape.
 */
class NotificationService
{
    public function __construct(
        protected NotificationTemplateService $templates,
    ) {}

    /**
     * @param  array{
     *   company_id?: int|null,
     *   entity?: string|null,
     *   user?: string|null,
     *   date?: string|null,
     *   title?: string|null,
     *   step?: string|null,
     *   reason?: string|null,
     *   process_id?: int|null,
     *   process_title?: string|null,
     *   task_title?: string|null,
     *   asset?: string|null,
     *   panel?: string|null,
     *   path?: string|null,
     *   link_params?: array<string, scalar|null>,
     *   approval_record_id?: int|null,
     *   approvable_type?: string|null,
     *   approvable_id?: int|null,
     * }  $payload
     */
    public function notify(User $user, string $eventKey, array $payload = []): void
    {
        $events = config('notifications.events', []);
        if (! is_array($events) || ! isset($events[$eventKey]) || ! is_array($events[$eventKey])) {
            throw new InvalidArgumentException("Bilinmeyen bildirim olayı: {$eventKey}");
        }
        $catalog = $events[$eventKey];

        $expectedCompanyId = isset($payload['company_id']) ? (int) $payload['company_id'] : null;
        if ($expectedCompanyId !== null && (int) $user->company_id !== $expectedCompanyId) {
            Log::warning('notification.tenant_mismatch', [
                'event' => $eventKey,
                'user_id' => $user->id,
                'user_company_id' => $user->company_id,
                'payload_company_id' => $expectedCompanyId,
            ]);

            return;
        }

        $companyId = $expectedCompanyId ?? (int) $user->company_id;
        $replacements = $this->buildReplacements($user, $payload);
        $rendered = $this->templates->render($eventKey, $companyId, $replacements, $catalog);
        $title = $rendered['title'];
        $body = $rendered['body'];

        $panel = $payload['panel'] ?? $catalog['panel'] ?? 'company';
        $pathTemplate = $payload['path'] ?? $catalog['path'] ?? '/';
        $path = $this->resolvePath((string) $pathTemplate, $payload['link_params'] ?? $payload);
        $actionUrl = $this->buildActionUrl((string) $panel, $path);

        $data = [
            'title' => $title,
            'message' => $body,
            'link' => $path,
            'panel' => $panel,
            'action_url' => $actionUrl,
            'entity' => $payload['entity'] ?? null,
            'approval_record_id' => $payload['approval_record_id'] ?? null,
            'approvable_type' => $payload['approvable_type'] ?? null,
            'approvable_id' => $payload['approvable_id'] ?? null,
            'process_id' => $payload['process_id'] ?? null,
        ];

        $group = (string) ($catalog['group'] ?? 'approvals');
        $forced = (bool) ($catalog['force'] ?? false) || $group === 'security';

        if ($forced || $this->wantsInApp($user, $group)) {
            $user->notify(new CatalogNotification(
                $eventKey,
                $data,
                $companyId,
            ));
        }

        if (($forced || $this->wantsEmail($user, $group, $eventKey, $catalog)) && filled($user->email)) {
            $mailer = app()->environment('testing')
                ? 'array'
                : (string) config('mail.default', 'smtp');

            Mail::mailer($mailer)->to($user->email)->queue(new NotificationMail(
                mailSubject: $title,
                heading: $title,
                body: $body,
                actionUrl: $actionUrl,
                recipientName: (string) ($user->name ?? ''),
            ));
        }
    }

    /**
     * Onay istendi — onaycı + (varsa) vekalet öncesi asıl onaycı.
     */
    public function notifyApprovalRequested(
        ApprovalRecord $record,
        User $effectiveApprover,
        Model $approvable,
        ApprovalStep $step,
        ?User $baseApprover = null,
    ): void {
        $payload = $this->approvalPayload($record, $approvable, $step);

        $targets = [$effectiveApprover];
        if ($baseApprover !== null && (int) $baseApprover->id !== (int) $effectiveApprover->id) {
            $targets[] = $baseApprover;
        }

        foreach ($targets as $target) {
            if ((int) $target->company_id !== (int) $record->company_id) {
                continue;
            }
            $this->notify($target, 'approval.requested', $payload);
        }
    }

    /**
     * Workflow nihai onay / red — talep sahibine.
     */
    public function notifyWorkflowOutcome(Model $approvable, string $outcome, ?string $reason = null): void
    {
        if (! in_array($outcome, ['approved', 'rejected', 'returned'], true)) {
            return;
        }

        $requester = $this->resolveRequester($approvable);
        if ($requester === null) {
            return;
        }

        $companyId = (int) ($approvable->getAttribute('company_id') ?? $requester->company_id);
        if ((int) $requester->company_id !== $companyId) {
            return;
        }

        $eventKey = $this->outcomeEventKey($approvable, $outcome);
        $entity = $this->entityLabel($approvable);
        [$panel, $path] = $this->panelPathForApprovable($approvable);

        $this->notify($requester, $eventKey, [
            'company_id' => $companyId,
            'entity' => $entity,
            'user' => $requester->name,
            'date' => now()->toDateString(),
            'reason' => $reason,
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->getKey(),
            'path' => $path,
            'panel' => $panel,
            'link_params' => ['id' => $approvable->getKey()],
        ]);
    }

    /**
     * Onboarding görev ataması.
     */
    public function notifyOnboardingTaskAssigned(OnboardingTask $task): void
    {
        if ($task->assigned_to === null) {
            return;
        }

        $assignee = User::query()->find($task->assigned_to);
        if ($assignee === null) {
            return;
        }

        if ((int) $assignee->company_id !== (int) $task->company_id) {
            return;
        }

        $this->notify($assignee, 'onboarding.task_assigned', [
            'company_id' => (int) $task->company_id,
            'task_title' => $task->title,
            'process_id' => $task->process_id,
            'process_title' => $task->process?->title ?? '',
            'entity' => $task->title,
            'user' => $assignee->name,
            'date' => now()->toDateString(),
            'link_params' => ['process_id' => $task->process_id],
        ]);
    }

    /**
     * Zimmet ataması — personel bildirimi.
     */
    public function notifyAssetAssigned(Asset $asset, User $assignee): void
    {
        if ((int) $assignee->company_id !== (int) $asset->company_id) {
            return;
        }

        $label = trim((string) ($asset->name ?? $asset->asset_code ?? 'Asset'));

        $this->notify($assignee, 'asset.assigned', [
            'company_id' => (int) $asset->company_id,
            'entity' => $label,
            'asset' => $label,
            'user' => $assignee->name,
            'date' => now()->toDateString(),
            'path' => '/assets',
            'panel' => 'portal',
        ]);
    }

    /**
     * Güvenlik olayı (tercihle kapatılamaz).
     */
    public function notifySecurity(User $user, string $eventKey): void
    {
        $this->notify($user, $eventKey, [
            'company_id' => (int) $user->company_id,
            'user' => $user->name,
            'date' => now()->toDateString(),
            'panel' => 'company',
            'path' => '/account/preferences',
        ]);
    }

    public function wantsInApp(User $user, string $group): bool
    {
        if ($group === 'security') {
            return true;
        }

        $prefs = $user->preferences ?? [];
        $inApp = data_get($prefs, 'notifications.in_app', []);

        if (! is_array($inApp)) {
            return true;
        }

        if (array_key_exists($group, $inApp)) {
            return (bool) $inApp[$group];
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    public function wantsEmail(User $user, string $group, string $eventKey = '', array $catalog = []): bool
    {
        if ($group === 'security' || (bool) ($catalog['force'] ?? false)) {
            return true;
        }

        // Hatırlatma: varsayılan e-posta kapalı; yalnız açık tercih
        if ($eventKey === 'approval.reminder') {
            $reminders = data_get($user->preferences ?? [], 'notifications.email.reminders');
            if ($reminders !== null) {
                return (bool) $reminders;
            }

            return false;
        }

        $prefs = $user->preferences ?? [];
        $emailPrefs = data_get($prefs, 'notifications.email', []);

        if (! is_array($emailPrefs)) {
            return (bool) ($catalog['email_default'] ?? true);
        }

        if (array_key_exists($group, $emailPrefs)) {
            return (bool) $emailPrefs[$group];
        }

        return (bool) ($catalog['email_default'] ?? true);
    }

    /**
     * @return array{
     *   in_app: array{approvals: bool, requests: bool, tasks: bool, documents: bool},
     *   email: array{approvals: bool, requests: bool, tasks: bool, documents: bool, reminders: bool}
     * }
     */
    public static function defaultChannelPreferences(): array
    {
        return [
            'in_app' => [
                'approvals' => true,
                'requests' => true,
                'tasks' => true,
                'documents' => true,
            ],
            'email' => [
                'approvals' => true,
                'requests' => true,
                'tasks' => true,
                'documents' => true,
                'reminders' => false,
            ],
        ];
    }

    /**
     * @deprecated 4C-2 — defaultChannelPreferences kullanın
     *
     * @return array{approvals: bool, requests: bool, tasks: bool}
     */
    public static function defaultEmailPreferences(): array
    {
        return [
            'approvals' => true,
            'requests' => true,
            'tasks' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function buildReplacements(User $user, array $payload): array
    {
        return [
            'user' => (string) ($payload['user'] ?? $user->name ?? ''),
            'entity' => (string) ($payload['entity'] ?? ''),
            'date' => (string) ($payload['date'] ?? now()->toDateString()),
            'step' => (string) ($payload['step'] ?? ''),
            'reason' => (string) ($payload['reason'] ?? ''),
            'task' => (string) ($payload['task_title'] ?? $payload['entity'] ?? ''),
            'process' => (string) ($payload['process_title'] ?? ''),
            'days' => (string) ($payload['days'] ?? $payload['threshold_days'] ?? ''),
            'asset' => (string) ($payload['asset'] ?? $payload['entity'] ?? ''),
            'title' => (string) ($payload['title'] ?? $payload['entity'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function resolvePath(string $template, array $params): string
    {
        return (string) preg_replace_callback('/\{(\w+)\}/', function (array $m) use ($params): string {
            $key = $m[1];
            $value = $params[$key] ?? '';

            return (string) $value;
        }, $template);
    }

    private function buildActionUrl(string $panel, string $path): string
    {
        $base = rtrim((string) config("app.frontend_urls.{$panel}", config('app.frontend_url')), '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalPayload(ApprovalRecord $record, Model $approvable, ApprovalStep $step): array
    {
        [$panel, $path] = $this->panelPathForApprovable($approvable);

        return [
            'company_id' => (int) $record->company_id,
            'entity' => $this->entityLabel($approvable),
            'step' => $step->name,
            'date' => now()->toDateString(),
            'approval_record_id' => $record->id,
            'approvable_type' => $record->approvable_type,
            'approvable_id' => $record->approvable_id,
            'path' => $path,
            'panel' => $panel === 'portal' ? 'company' : $panel,
            'link_params' => ['id' => $approvable->getKey()],
        ];
    }

    /**
     * @return array{0: string, 1: string} panel, path
     */
    private function panelPathForApprovable(Model $approvable): array
    {
        return match (true) {
            $approvable instanceof LeaveRequest => ['portal', '/leaves'],
            $approvable instanceof ExpenseClaim => ['portal', '/expenses'],
            $approvable instanceof SalaryReviewPeriod => ['company', '/employees/salary-reviews/'.$approvable->getKey()],
            default => ['portal', '/leaves'],
        };
    }

    private function outcomeEventKey(Model $approvable, string $outcome): string
    {
        if ($approvable instanceof LeaveRequest) {
            return $outcome === 'approved' ? 'leave.approved' : ($outcome === 'returned' ? 'approval.returned' : 'leave.rejected');
        }

        if ($approvable instanceof ExpenseClaim) {
            return $outcome === 'approved' ? 'expense.approved' : ($outcome === 'returned' ? 'approval.returned' : 'expense.rejected');
        }

        return match ($outcome) {
            'approved' => 'approval.approved',
            'returned' => 'approval.returned',
            default => 'approval.rejected',
        };
    }

    private function entityLabel(Model $approvable): string
    {
        if ($approvable instanceof LeaveRequest) {
            return __('messages.notifications.entity_leave');
        }

        if ($approvable instanceof ExpenseClaim) {
            return __('messages.notifications.entity_expense');
        }

        if ($approvable instanceof SalaryReviewPeriod) {
            return __('messages.notifications.entity_salary_review');
        }

        return class_basename($approvable);
    }

    private function resolveRequester(Model $approvable): ?User
    {
        if ($approvable instanceof SalaryReviewPeriod) {
            $userId = $approvable->submitted_by ?? $approvable->created_by;
            if ($userId) {
                return User::query()->find($userId);
            }

            return null;
        }

        if (method_exists($approvable, 'user')) {
            $user = $approvable->user;
            if ($user instanceof User) {
                return $user;
            }
        }

        $userId = $approvable->getAttribute('user_id');
        if ($userId) {
            return User::query()->find($userId);
        }

        return null;
    }
}
