<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dashboard extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    public const MAX_WIDGETS = 20;

    protected $fillable = [
        'company_id',
        'owner_id',
        'name',
        'description',
        'layout',
        'global_filters',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'layout' => 'array',
        'global_filters' => 'array',
        'is_system' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DashboardShare::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function widgets(): array
    {
        $layout = is_array($this->layout) ? $this->layout : [];
        $widgets = $layout['widgets'] ?? [];

        return is_array($widgets) ? array_values($widgets) : [];
    }

    public function scopeAccessibleBy($query, User $user)
    {
        $userId = (int) $user->id;
        $roleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $query->where(function ($q) use ($userId, $roleIds) {
            $q->where('owner_id', $userId)
                ->orWhere('is_system', true)
                ->orWhereHas('shares', function ($sq) use ($userId, $roleIds) {
                    $sq->where(function ($s) use ($userId, $roleIds) {
                        $s->where('user_id', $userId);
                        if ($roleIds !== []) {
                            $s->orWhereIn('role_id', $roleIds);
                        }
                    });
                });
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        if ((int) $this->owner_id === (int) $user->id || $this->is_system) {
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

    public function canEdit(User $user): bool
    {
        if ((int) $this->owner_id === (int) $user->id) {
            return true;
        }

        return $this->shares()
            ->where('level', 'editor')
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
}
