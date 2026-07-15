<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_workflow_id',
        'step_order',
        'name',
        'approver_type',
        'specific_user_id',
        'specific_role',
        'is_required',
        'can_skip',
        'timeout_hours',
        'timeout_action',
        'condition',
        'parallel_group',
        'completion_policy',
        'escalation_days',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'can_skip' => 'boolean',
        'step_order' => 'integer',
        'timeout_hours' => 'integer',
        'condition' => 'array',
        'parallel_group' => 'integer',
        'completion_policy' => 'string',
        'escalation_days' => 'integer',
    ];

    // Onaylayıcı tipleri (legacy + Faz 4B)
    const APPROVER_DIRECT_MANAGER = 'direct_manager';

    const APPROVER_DEPARTMENT_HEAD = 'department_head';

    const APPROVER_SPECIFIC_USER = 'specific_user';

    const APPROVER_SPECIFIC_ROLE = 'specific_role';

    const APPROVER_HR = 'hr';

    const APPROVER_CFO = 'cfo';

    const APPROVER_CEO = 'ceo';

    const APPROVER_DYNAMIC_MANAGER = 'dynamic_manager';

    const APPROVER_DYNAMIC_SKIP_MANAGER = 'dynamic_skip_manager';

    const APPROVER_ROLE = 'role';

    const APPROVER_USER = 'user';

    public static function getApproverTypes(): array
    {
        return [
            self::APPROVER_DYNAMIC_MANAGER => 'Direkt Yönetici',
            self::APPROVER_DYNAMIC_SKIP_MANAGER => 'Üst Yönetici (skip-level)',
            self::APPROVER_DIRECT_MANAGER => 'Direkt Yönetici (legacy)',
            self::APPROVER_DEPARTMENT_HEAD => 'Departman Yöneticisi',
            self::APPROVER_USER => 'Belirli Kullanıcı',
            self::APPROVER_SPECIFIC_USER => 'Belirli Kullanıcı (legacy)',
            self::APPROVER_ROLE => 'Belirli Rol',
            self::APPROVER_SPECIFIC_ROLE => 'Belirli Rol (legacy)',
            self::APPROVER_HR => 'İK Departmanı',
            self::APPROVER_CFO => 'Finans Müdürü',
            self::APPROVER_CEO => 'Genel Müdür',
        ];
    }

    // Timeout aksiyonları
    const TIMEOUT_ESCALATE = 'escalate';

    const TIMEOUT_AUTO_APPROVE = 'auto_approve';

    const TIMEOUT_AUTO_REJECT = 'auto_reject';

    const COMPLETION_ALL = 'all';

    const COMPLETION_ANY = 'any';

    // Relationships
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function specificUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specific_user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ApprovalRecord::class);
    }

    /**
     * Bu adım için onaylayıcıyı bul (vekalet uygulanır).
     * Dinamik hiyerarşi çözülemezse adım atlanmaz → hr_manager fallback.
     */
    public function findApprover(Model $approvable): ?User
    {
        $approver = $this->getBaseApprover($approvable);

        if (! $approver && $this->usesHierarchyApprover()) {
            \Illuminate\Support\Facades\Log::warning('approval.approver.unresolved_hr_fallback', [
                'step_id' => $this->id,
                'approver_type' => $this->approver_type,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'company_id' => $approvable->company_id,
            ]);

            $approver = $this->resolveHrManagerFallback((int) $approvable->company_id);
        }

        if (! $approver) {
            return null;
        }

        $delegate = ApprovalDelegation::findActiveDelegate(
            $approver->id,
            $approvable->company_id,
            class_basename($approvable)
        );

        return $delegate ?? $approver;
    }

    /**
     * Hiyerarşi / departman tipi — çözülemezse sonraki adıma atlama YOK.
     */
    public function usesHierarchyApprover(): bool
    {
        return in_array($this->approver_type, [
            self::APPROVER_DYNAMIC_MANAGER,
            self::APPROVER_DIRECT_MANAGER,
            self::APPROVER_DYNAMIC_SKIP_MANAGER,
            self::APPROVER_DEPARTMENT_HEAD,
        ], true);
    }

    protected function resolveHrManagerFallback(int $companyId): ?User
    {
        return User::where('company_id', $companyId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'hr_manager'))
            ->orderBy('id')
            ->first();
    }

    /**
     * Temel onaylayıcı (vekalet hariç) — bildirim çift hedefi için.
     */
    public function resolveBaseApprover(Model $approvable): ?User
    {
        $approver = $this->getBaseApprover($approvable);

        if (! $approver && $this->usesHierarchyApprover()) {
            $approver = $this->resolveHrManagerFallback((int) $approvable->company_id);
        }

        return $approver;
    }

    /**
     * Temel onaylayıcı (vekalet hariç).
     * Hiyerarşi: Employee.manager_id → manager.user_id (Faz 2 DataScope ile aynı).
     */
    protected function getBaseApprover(Model $approvable): ?User
    {
        // Belirli kullanıcı / rol / unvan: talep sahibi (user) gerekmez
        // (salary_review gibi entity'lerde user ilişkisi yoktur).
        if (in_array($this->approver_type, [
            self::APPROVER_USER,
            self::APPROVER_SPECIFIC_USER,
            self::APPROVER_ROLE,
            self::APPROVER_SPECIFIC_ROLE,
            self::APPROVER_HR,
            self::APPROVER_CFO,
            self::APPROVER_CEO,
        ], true)) {
            return match ($this->approver_type) {
                self::APPROVER_USER,
                self::APPROVER_SPECIFIC_USER => $this->specificUser,

                self::APPROVER_ROLE,
                self::APPROVER_SPECIFIC_ROLE => User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', $this->specific_role))
                    ->first(),

                self::APPROVER_HR => User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'hr_manager'))
                    ->first(),

                self::APPROVER_CFO => User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'cfo'))
                    ->first(),

                self::APPROVER_CEO => User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'ceo'))
                    ->first(),

                default => null,
            };
        }

        $requestingUser = $approvable->user ?? null;

        if (! $requestingUser) {
            return null;
        }

        $employee = $requestingUser->relationLoaded('employee')
            ? $requestingUser->employee
            : $requestingUser->employee()->with(['manager.user', 'department.manager'])->first();

        return match ($this->approver_type) {
            self::APPROVER_DYNAMIC_MANAGER,
            self::APPROVER_DIRECT_MANAGER => $this->resolveDirectManager($employee),

            self::APPROVER_DYNAMIC_SKIP_MANAGER => $this->resolveSkipManager($employee),

            self::APPROVER_DEPARTMENT_HEAD => $this->resolveDepartmentHead($employee),

            default => null,
        };
    }

    protected function resolveDirectManager(?Employee $employee): ?User
    {
        if (! $employee?->manager_id) {
            return null;
        }

        $manager = $employee->relationLoaded('manager')
            ? $employee->manager
            : $employee->manager()->with('user')->first();

        return $manager?->user;
    }

    protected function resolveSkipManager(?Employee $employee): ?User
    {
        if (! $employee?->manager_id) {
            return null;
        }

        $manager = $employee->relationLoaded('manager')
            ? $employee->manager
            : $employee->manager()->with('manager.user')->first();

        if (! $manager?->manager_id) {
            return null;
        }

        $skip = $manager->relationLoaded('manager')
            ? $manager->manager
            : $manager->manager()->with('user')->first();

        return $skip?->user;
    }

    protected function resolveDepartmentHead(?Employee $employee): ?User
    {
        if (! $employee?->department_id) {
            return null;
        }

        $department = $employee->relationLoaded('department')
            ? $employee->department
            : $employee->department()->with('manager')->first();

        // Department.manager_id → User
        return $department?->manager;
    }
}
