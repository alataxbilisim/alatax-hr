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
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'can_skip' => 'boolean',
        'step_order' => 'integer',
        'timeout_hours' => 'integer',
    ];

    // Onaylayıcı tipleri
    const APPROVER_DIRECT_MANAGER = 'direct_manager';

    const APPROVER_DEPARTMENT_HEAD = 'department_head';

    const APPROVER_SPECIFIC_USER = 'specific_user';

    const APPROVER_SPECIFIC_ROLE = 'specific_role';

    const APPROVER_HR = 'hr';

    const APPROVER_CFO = 'cfo';

    const APPROVER_CEO = 'ceo';

    public static function getApproverTypes(): array
    {
        return [
            self::APPROVER_DIRECT_MANAGER => 'Direkt Yönetici',
            self::APPROVER_DEPARTMENT_HEAD => 'Departman Yöneticisi',
            self::APPROVER_SPECIFIC_USER => 'Belirli Kullanıcı',
            self::APPROVER_SPECIFIC_ROLE => 'Belirli Rol',
            self::APPROVER_HR => 'İK Departmanı',
            self::APPROVER_CFO => 'Finans Müdürü',
            self::APPROVER_CEO => 'Genel Müdür',
        ];
    }

    // Timeout aksiyonları
    const TIMEOUT_ESCALATE = 'escalate';

    const TIMEOUT_AUTO_APPROVE = 'auto_approve';

    const TIMEOUT_AUTO_REJECT = 'auto_reject';

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
     * Bu adım için onaylayıcıyı bul
     */
    public function findApprover(Model $approvable): ?User
    {
        // Önce vekalet kontrolü yap
        $approver = $this->getBaseApprover($approvable);

        if (! $approver) {
            return null;
        }

        // Vekalet var mı kontrol et
        $delegate = ApprovalDelegation::findActiveDelegate(
            $approver->id,
            $approvable->company_id,
            class_basename($approvable)
        );

        return $delegate ?? $approver;
    }

    /**
     * Temel onaylayıcıyı bul (vekalet hariç)
     */
    protected function getBaseApprover(Model $approvable): ?User
    {
        $requestingUser = $approvable->user ?? null;

        if (! $requestingUser) {
            return null;
        }

        switch ($this->approver_type) {
            case self::APPROVER_DIRECT_MANAGER:
                return $requestingUser->manager;

            case self::APPROVER_DEPARTMENT_HEAD:
                $department = $requestingUser->department;

                return $department?->head;

            case self::APPROVER_SPECIFIC_USER:
                return $this->specificUser;

            case self::APPROVER_SPECIFIC_ROLE:
                return User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', $this->specific_role))
                    ->first();

            case self::APPROVER_HR:
                return User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'hr_manager'))
                    ->first();

            case self::APPROVER_CFO:
                return User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'cfo'))
                    ->first();

            case self::APPROVER_CEO:
                return User::where('company_id', $approvable->company_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'ceo'))
                    ->first();

            default:
                return null;
        }
    }
}
