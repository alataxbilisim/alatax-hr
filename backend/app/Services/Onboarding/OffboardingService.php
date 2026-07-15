<?php

namespace App\Services\Onboarding;

use App\Models\ActivityLog;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Lookup;
use App\Models\OnboardingProcess;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Services\DefaultCompanyHrSeedService;
use App\Services\LookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * İşten çıkış (offboarding) — mevcut onboarding süreç/görev motorunu kullanır.
 */
class OffboardingService
{
    public const ACTION_ASSET_RETURN = 'asset_return';

    public const ACTION_REVOKE_PORTAL = 'revoke_portal';

    public function __construct(
        protected OnboardingProcessService $processService,
        protected LookupService $lookups,
    ) {}

    /**
     * @param  array{
     *   termination_reason_code: string,
     *   termination_date: string,
     *   exit_notes?: string|null,
     *   template_id?: int|null,
     *   assigned_to?: int|null
     * }  $data
     */
    public function start(Employee $employee, array $data, ?int $actorId = null): OnboardingProcess
    {
        if ($employee->status === 'terminated') {
            throw ValidationException::withMessages([
                'employee' => ['Personel zaten işten çıkmış.'],
            ]);
        }

        if ($employee->user_id === null) {
            throw ValidationException::withMessages([
                'user_id' => ['İşten çıkış için personelin kullanıcı kaydı (portal/hesap) zorunludur.'],
            ]);
        }

        $this->lookups->assertValid(
            LookupService::TYPE_TERMINATION_REASON,
            $data['termination_reason_code'],
            (int) $employee->company_id,
            'termination_reason_code'
        );

        $existing = OnboardingProcess::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('process_type', OnboardingProcess::TYPE_OFFBOARDING)
            ->active()
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'process' => ['Bu personel için açık bir işten çıkış süreci zaten var.'],
            ]);
        }

        $templateId = isset($data['template_id']) ? (int) $data['template_id'] : null;
        if (! $templateId) {
            $template = $this->processService->resolveDefaultTemplate(
                (int) $employee->company_id,
                OnboardingTemplate::TYPE_OFFBOARDING
            );
            if ($template === null) {
                $template = app(DefaultOffboardingTemplateService::class)
                    ->ensureForCompany((int) $employee->company_id);
            }
            $templateId = (int) $template->id;
        }

        $remaining = $this->calculateRemainingAnnualLeaveDays($employee);

        $process = $this->processService->startProcess([
            'company_id' => (int) $employee->company_id,
            'user_id' => (int) $employee->user_id,
            'employee_id' => (int) $employee->id,
            'template_id' => $templateId,
            'title' => 'İşten çıkış — '.($employee->user?->name ?? $employee->employee_code),
            'start_date' => now()->toDateString(),
            'target_end_date' => $data['termination_date'],
            'notes' => $data['exit_notes'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? $actorId,
            'created_by' => $actorId,
            'process_type' => OnboardingProcess::TYPE_OFFBOARDING,
            'termination_reason_code' => $data['termination_reason_code'],
            'termination_date' => $data['termination_date'],
            'exit_notes' => $data['exit_notes'] ?? null,
            'remaining_leave_days' => $remaining,
        ]);

        $this->enrichSmartTasks($process->fresh(['tasks']), $employee);

        // Personel süreç boyunca AKTİF kalır — burada status değiştirilmez
        ActivityLog::log(
            'create',
            $process,
            'İşten çıkış sihirbazı ile süreç açıldı (personel aktif)'
        );

        return $process->fresh(['user', 'employee', 'template', 'tasks.assignedTo']);
    }

    public function enrichSmartTasks(OnboardingProcess $process, ?Employee $employee = null): void
    {
        if (! $process->isOffboarding()) {
            return;
        }

        $employee ??= $process->employee
            ?? Employee::query()
                ->where('company_id', $process->company_id)
                ->where('id', $process->employee_id)
                ->first();

        if ($employee === null || $employee->user_id === null) {
            return;
        }

        foreach ($process->tasks as $task) {
            $action = $task->data['action_key'] ?? null;
            if ($action !== self::ACTION_ASSET_RETURN) {
                continue;
            }

            $open = AssetAssignment::query()
                ->where('user_id', $employee->user_id)
                ->active()
                ->with(['asset:id,name,asset_code,brand,model,serial_number'])
                ->get()
                ->map(fn (AssetAssignment $a) => [
                    'id' => $a->id,
                    'asset_id' => $a->asset_id,
                    'asset_name' => $a->asset?->name,
                    'asset_code' => $a->asset?->asset_code,
                    'assigned_date' => optional($a->assigned_date)?->toDateString(),
                ])
                ->values()
                ->all();

            $task->update([
                'data' => array_merge($task->data ?? [], [
                    'action_key' => self::ACTION_ASSET_RETURN,
                    'open_assignments' => $open,
                    'open_count' => count($open),
                ]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCompleteTask(OnboardingProcess $process, OnboardingTask $task): void
    {
        if (! $process->isOffboarding()) {
            return;
        }

        if (! in_array($process->status, [
            OnboardingProcess::STATUS_PENDING,
            OnboardingProcess::STATUS_IN_PROGRESS,
        ], true)) {
            throw ValidationException::withMessages([
                'process' => ['Süreç aktif değil.'],
            ]);
        }

        $action = $task->data['action_key'] ?? null;
        if ($action === self::ACTION_ASSET_RETURN) {
            $userId = $process->user_id;
            $openCount = AssetAssignment::query()
                ->where('user_id', $userId)
                ->active()
                ->count();

            if ($openCount > 0) {
                throw ValidationException::withMessages([
                    'task' => ["Açık zimmet iade edilmeden bu görev tamamlanamaz ({$openCount} adet)."],
                ]);
            }
        }
    }

    public function afterTaskCompleted(OnboardingProcess $process, OnboardingTask $task): void
    {
        if (! $process->isOffboarding()) {
            return;
        }

        $action = $task->data['action_key'] ?? null;
        if ($action === self::ACTION_REVOKE_PORTAL) {
            $this->revokePortalAccessKeepingProcessLink($process);
        }
    }

    /**
     * Portal kullanıcısını pasifleştirir; süreç user_id / employee_id bağını korur.
     * employee.user_id null yapılır (mevcut portal-access revoke ile uyumlu).
     */
    public function revokePortalAccessKeepingProcessLink(OnboardingProcess $process): void
    {
        $employee = $process->employee
            ?? Employee::query()->where('id', $process->employee_id)->first();

        $user = User::query()->find($process->user_id);
        if ($user !== null && $user->is_active) {
            $user->update(['is_active' => false]);
        }

        if ($employee !== null && $employee->user_id !== null) {
            $employee->update(['user_id' => null]);
            ActivityLog::log('update', $employee, 'Offboarding: portal erişimi kapatıldı');
        }
    }

    public function finalize(OnboardingProcess $process, ?int $actorId = null): OnboardingProcess
    {
        if (! $process->isOffboarding()) {
            throw ValidationException::withMessages([
                'process' => ['Yalnızca işten çıkış süreçleri tamamlanabilir.'],
            ]);
        }

        if (! in_array($process->status, [
            OnboardingProcess::STATUS_PENDING,
            OnboardingProcess::STATUS_IN_PROGRESS,
        ], true)) {
            throw ValidationException::withMessages([
                'process' => ['Süreç zaten kapalı.'],
            ]);
        }

        $openRequired = $process->tasks()
            ->where('is_required', true)
            ->whereNotIn('status', [
                OnboardingTask::STATUS_COMPLETED,
                OnboardingTask::STATUS_SKIPPED,
            ])
            ->count();

        if ($openRequired > 0) {
            throw ValidationException::withMessages([
                'tasks' => ['Tüm zorunlu görevler tamamlanmadan çıkış kapatılamaz.'],
            ]);
        }

        return DB::transaction(function () use ($process, $actorId) {
            $employee = $process->employee
                ?? Employee::query()
                    ->where('company_id', $process->company_id)
                    ->where('id', $process->employee_id)
                    ->first();

            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee' => ['Süreçle ilişkili personel bulunamadı.'],
                ]);
            }

            $old = $employee->getOriginal();
            $employee->update([
                'status' => 'terminated',
                'termination_date' => $process->termination_date?->toDateString()
                    ?? now()->toDateString(),
                'termination_reason' => $process->termination_reason_code,
            ]);

            $process->update([
                'status' => OnboardingProcess::STATUS_COMPLETED,
                'actual_end_date' => now(),
                'progress' => 100,
                'updated_by' => $actorId,
            ]);

            ActivityLog::log(
                'update',
                $employee,
                'İşten çıkış tamamlandı — personel pasif (terminated)',
                $old,
                $employee->fresh()->toArray()
            );
            ActivityLog::log('update', $process, 'Offboarding süreci tamamlandı');

            return $process->fresh(['user', 'employee', 'template', 'tasks']);
        });
    }

    public function cancel(OnboardingProcess $process, ?int $actorId = null): OnboardingProcess
    {
        if (! $process->isOffboarding()) {
            throw ValidationException::withMessages([
                'process' => ['Yalnızca işten çıkış süreçleri iptal edilebilir.'],
            ]);
        }

        if ($process->status === OnboardingProcess::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'process' => ['Tamamlanmış süreç iptal edilemez.'],
            ]);
        }

        if ($process->status === OnboardingProcess::STATUS_CANCELLED) {
            return $process;
        }

        return DB::transaction(function () use ($process, $actorId) {
            $process->tasks()
                ->whereNotIn('status', [
                    OnboardingTask::STATUS_COMPLETED,
                    OnboardingTask::STATUS_SKIPPED,
                ])
                ->update(['status' => OnboardingTask::STATUS_SKIPPED]);

            $process->update([
                'status' => OnboardingProcess::STATUS_CANCELLED,
                'updated_by' => $actorId,
            ]);

            // Personel aktif kalır — bilerek status dokunulmaz
            ActivityLog::log('update', $process, 'Offboarding süreci iptal edildi (personel aktif)');

            return $process->fresh(['user', 'employee', 'template', 'tasks']);
        });
    }

    public function calculateRemainingAnnualLeaveDays(Employee $employee): float
    {
        if ($employee->user_id === null) {
            return 0.0;
        }

        $annual = LeaveType::query()
            ->where('company_id', $employee->company_id)
            ->where('system_code', DefaultCompanyHrSeedService::ANNUAL_SYSTEM_CODE)
            ->first();

        if ($annual === null) {
            return 0.0;
        }

        $balance = LeaveBalance::query()
            ->where('company_id', $employee->company_id)
            ->where('user_id', $employee->user_id)
            ->where('leave_type_id', $annual->id)
            ->where('year', (int) now()->year)
            ->first();

        if ($balance === null) {
            return 0.0;
        }

        return (float) $balance->remaining_days;
    }

    /**
     * @return array<string, mixed>
     */
    public function clearancePayload(OnboardingProcess $process): array
    {
        if (! $process->isOffboarding()) {
            throw ValidationException::withMessages([
                'process' => ['İbraname yalnızca işten çıkış süreçleri için üretilir.'],
            ]);
        }

        $process->loadMissing(['user', 'employee', 'company', 'template']);

        $employee = $process->employee;
        $returnedAssets = AssetAssignment::query()
            ->where('user_id', $process->user_id)
            ->whereNotNull('return_date')
            ->with(['asset:id,name,asset_code'])
            ->get()
            ->map(fn (AssetAssignment $a) => [
                'name' => $a->asset?->name,
                'code' => $a->asset?->asset_code,
                'return_date' => optional($a->return_date)?->toDateString(),
            ])
            ->values()
            ->all();

        $reason = Lookup::query()
            ->where('lookup_type', LookupService::TYPE_TERMINATION_REASON)
            ->where('value', $process->termination_reason_code)
            ->where(function ($q) use ($process) {
                $q->whereNull('company_id')->orWhere('company_id', $process->company_id);
            })
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        return [
            'company_name' => $process->company?->name,
            'company_logo' => $process->company?->logo ?? null,
            'employee_name' => $process->user?->name ?? $employee?->user?->name,
            'employee_code' => $employee?->employee_code,
            'national_id' => $employee?->national_id,
            'termination_reason_code' => $process->termination_reason_code,
            'termination_reason_label' => $reason?->label ?? $process->termination_reason_code,
            'termination_date' => optional($process->termination_date)?->toDateString(),
            'remaining_leave_days' => $process->remaining_leave_days,
            'returned_assets' => $returnedAssets,
            'declaration' => 'Yukarıda belirtilen zimmetler iade edilmiş olup, tüm hakları teslim alınmıştır.',
            'signature_fields' => ['employee', 'hr', 'manager'],
        ];
    }
}
