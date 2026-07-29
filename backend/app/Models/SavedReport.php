<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedReport extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'folder_id',
        'user_id',
        'name',
        'description',
        'dataset_key',
        'config',
        'is_favorite',
        'is_shared',
        'share_user_ids',
        'share_role_ids',
        'is_system',
        'sort_order',
        'cache_ttl_seconds',
        'module_key',
        'system_key',
    ];

    protected $casts = [
        'config' => 'array',
        'share_user_ids' => 'array',
        'share_role_ids' => 'array',
        'is_favorite' => 'boolean',
        'is_shared' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
        'cache_ttl_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ReportFolder::class, 'folder_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ReportShare::class, 'saved_report_id');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Kullanıcının erişebileceği tanımlar: sahip + legacy JSON paylaşım + report_shares.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $userId = (int) $user->id;
        $roleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();
        $deptId = $user->employee?->department_id;

        return $query->where(function ($q) use ($userId, $roleIds, $deptId) {
            $q->where('user_id', $userId)
                ->orWhere('is_shared', true)
                ->orWhere('is_system', true);

            $q->orWhere(function ($q2) use ($userId) {
                $q2->whereNotNull('share_user_ids')
                    ->whereRaw('share_user_ids @> ?::jsonb', [json_encode([$userId])]);
            });

            if ($roleIds !== []) {
                $q->orWhere(function ($q2) use ($roleIds) {
                    foreach ($roleIds as $roleId) {
                        $q2->orWhereRaw('share_role_ids @> ?::jsonb', [json_encode([$roleId])]);
                    }
                });
            }

            $q->orWhereHas('shares', function ($sq) use ($userId, $roleIds, $deptId) {
                $sq->where(function ($s) use ($userId, $roleIds, $deptId) {
                    $s->where('user_id', $userId);
                    if ($roleIds !== []) {
                        $s->orWhereIn('role_id', $roleIds);
                    }
                    if ($deptId) {
                        $s->orWhere('department_id', (int) $deptId);
                    }
                });
            });
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        // Global sistem şablonu (company_id null)
        if ($this->is_system && $this->company_id === null) {
            return true;
        }
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }
        if ($this->is_shared || $this->is_system) {
            return true;
        }
        $shareUsers = is_array($this->share_user_ids) ? $this->share_user_ids : [];
        if (in_array((int) $user->id, array_map('intval', $shareUsers), true)) {
            return true;
        }
        $shareRoles = is_array($this->share_role_ids) ? $this->share_role_ids : [];
        $userRoleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count(array_intersect(array_map('intval', $shareRoles), $userRoleIds)) > 0) {
            return true;
        }

        return $this->shares()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                $roleIds = $user->roles->pluck('id')->all();
                if ($roleIds !== []) {
                    $q->orWhereIn('role_id', $roleIds);
                }
                $deptId = $user->employee?->department_id;
                if ($deptId) {
                    $q->orWhere('department_id', (int) $deptId);
                }
            })
            ->exists();
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function shareLevelFor(User $user): ?string
    {
        if ($this->isOwner($user)) {
            return 'owner';
        }
        $share = $this->shares()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                $roleIds = $user->roles->pluck('id')->all();
                if ($roleIds !== []) {
                    $q->orWhereIn('role_id', $roleIds);
                }
                $deptId = $user->employee?->department_id;
                if ($deptId) {
                    $q->orWhere('department_id', (int) $deptId);
                }
            })
            ->orderByRaw("CASE level WHEN 'editor' THEN 0 ELSE 1 END")
            ->first();

        return $share?->level;
    }

    public function canEdit(User $user): bool
    {
        // Global sistem şablonu salt okunur — kopyala ve özelleştir
        if ($this->is_system && $this->company_id === null) {
            return false;
        }
        if ($this->isOwner($user)) {
            return true;
        }
        if ($this->shareLevelFor($user) === 'editor') {
            return true;
        }

        return false;
    }

    public function canDelete(User $user): bool
    {
        if ($this->is_system && $this->company_id === null) {
            return false;
        }

        return $this->isOwner($user);
    }

    public function canManageShares(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function canTransfer(User $user): bool
    {
        return $this->isOwner($user) || $user->can('reports.definitions.transfer');
    }
}
