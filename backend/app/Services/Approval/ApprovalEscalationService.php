<?php

namespace App\Services\Approval;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalEscalationAlert;
use App\Models\ApprovalRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pending onay kayıtları için hatırlatma + üst bilgilendirme.
 * Yetki DEVRETMEZ (approver_id değişmez); yalnız bildirir.
 */
class ApprovalEscalationService
{
    /** Eşik sonrası eskalasyon gecikmesi (gün). */
    public const ESCALATE_AFTER_EXTRA_DAYS = 2;

    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * @return array{checked: int, reminded: int, escalated: int, skipped: int, failed: int}
     */
    public function processAllActiveCompanies(?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $summary = [
            'checked' => 0,
            'reminded' => 0,
            'escalated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $companies = Company::query()
            ->where('status', CompanyStatus::Active)
            ->orderBy('id')
            ->pluck('id');

        foreach ($companies as $companyId) {
            try {
                $result = $this->processCompany((int) $companyId, $today);
                $summary['checked'] += $result['checked'];
                $summary['reminded'] += $result['reminded'];
                $summary['escalated'] += $result['escalated'];
                $summary['skipped'] += $result['skipped'];
            } catch (Throwable $e) {
                $summary['failed']++;
                Log::error('scheduler.approval_escalation.company_failed', [
                    'company_id' => $companyId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('scheduler.approval_escalation.finished', $summary);

        return $summary;
    }

    /**
     * @return array{checked: int, reminded: int, escalated: int, skipped: int}
     */
    public function processCompany(int $companyId, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $checked = 0;
        $reminded = 0;
        $escalated = 0;
        $skipped = 0;

        $records = ApprovalRecord::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->where('is_current', true)
            ->with(['step', 'workflow', 'approver.employee.manager.user', 'approvable'])
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $checked++;

            $threshold = $this->resolveEscalationDays($record);
            if ($threshold === null || $threshold < 1) {
                $skipped++;

                continue;
            }

            $openedAt = $record->created_at ?? $today;
            $pendingDays = $openedAt->copy()->startOfDay()->diffInDays($today->copy()->startOfDay());

            if ($pendingDays >= $threshold + self::ESCALATE_AFTER_EXTRA_DAYS) {
                if ($this->sendOnce($record, ApprovalEscalationAlert::LEVEL_ESCALATED, $pendingDays)) {
                    $escalated++;
                } else {
                    $skipped++;
                }
            } elseif ($pendingDays >= $threshold) {
                if ($this->sendOnce($record, ApprovalEscalationAlert::LEVEL_REMINDER, $pendingDays)) {
                    $reminded++;
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }

        return compact('checked', 'reminded', 'escalated', 'skipped');
    }

    protected function resolveEscalationDays(ApprovalRecord $record): ?int
    {
        $stepDays = $record->step?->escalation_days;
        if ($stepDays !== null) {
            return (int) $stepDays;
        }

        $workflowDays = $record->workflow?->escalation_days;
        if ($workflowDays !== null) {
            return (int) $workflowDays;
        }

        return null;
    }

    protected function sendOnce(ApprovalRecord $record, string $level, int $pendingDays): bool
    {
        return (bool) DB::transaction(function () use ($record, $level, $pendingDays): bool {
            $alert = ApprovalEscalationAlert::query()->firstOrCreate(
                [
                    'approval_record_id' => $record->id,
                    'alert_level' => $level,
                ],
                [
                    'company_id' => $record->company_id,
                    'notified_at' => now(),
                ]
            );

            if (! $alert->wasRecentlyCreated) {
                return false;
            }

            if ($level === ApprovalEscalationAlert::LEVEL_REMINDER) {
                $this->notifyReminder($record, $pendingDays);
            } else {
                $this->notifyEscalated($record, $pendingDays);
            }

            return true;
        });
    }

    protected function notifyReminder(ApprovalRecord $record, int $pendingDays): void
    {
        $approver = $record->approver;
        if (! $approver instanceof User) {
            return;
        }

        $this->notifications->notify($approver, 'approval.reminder', $this->payload($record, $pendingDays));
    }

    protected function notifyEscalated(ApprovalRecord $record, int $pendingDays): void
    {
        $payload = $this->payload($record, $pendingDays);
        $targets = $this->resolveEscalationTargets($record);

        foreach ($targets as $user) {
            $this->notifications->notify($user, 'approval.escalated', $payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(ApprovalRecord $record, int $pendingDays): array
    {
        $entity = class_basename((string) $record->approvable_type);

        return [
            'company_id' => (int) $record->company_id,
            'entity' => $entity,
            'step' => $record->step?->name ?? ('#'.$record->step_order),
            'days' => (string) $pendingDays,
            'approval_record_id' => $record->id,
            'panel' => 'company',
            'path' => '/leaves',
        ];
    }

    /**
     * Üst: onaycının yönetici zinciri; yoksa company admin / İK.
     * Yetkiyi DEĞİŞTİRMEZ.
     *
     * @return list<User>
     */
    protected function resolveEscalationTargets(ApprovalRecord $record): array
    {
        $targets = [];
        $seen = [];

        $approver = $record->approver;
        if ($approver instanceof User) {
            $managerUser = $this->resolveManagerOfUser($approver);
            if ($managerUser
                && (int) $managerUser->company_id === (int) $record->company_id
                && (int) $managerUser->id !== (int) $approver->id) {
                $targets[] = $managerUser;
                $seen[$managerUser->id] = true;
            }
        }

        if ($targets === []) {
            foreach ($this->resolveCompanyAdmins((int) $record->company_id) as $admin) {
                if (isset($seen[$admin->id])) {
                    continue;
                }
                if ($approver && (int) $admin->id === (int) $approver->id) {
                    continue;
                }
                $targets[] = $admin;
                $seen[$admin->id] = true;
            }
        }

        return $targets;
    }

    protected function resolveManagerOfUser(User $user): ?User
    {
        $employee = $user->relationLoaded('employee')
            ? $user->employee
            : $user->employee()->with(['manager.user'])->first();

        if (! $employee instanceof Employee || ! $employee->manager_id) {
            return null;
        }

        $manager = $employee->relationLoaded('manager')
            ? $employee->manager
            : $employee->manager()->with('user')->first();

        return $manager?->user;
    }

    /**
     * @return list<User>
     */
    protected function resolveCompanyAdmins(int $companyId): array
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('type', UserType::CompanyAdmin)
                    ->orWhereHas('roles', function ($r): void {
                        $r->whereIn('name', ['admin', 'hr_manager']);
                    });
            })
            ->orderBy('id')
            ->get()
            ->all();
    }
}
