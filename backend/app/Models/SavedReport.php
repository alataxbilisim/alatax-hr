<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
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
    ];

    protected $casts = [
        'config' => 'array',
        'share_user_ids' => 'array',
        'share_role_ids' => 'array',
        'is_favorite' => 'boolean',
        'is_shared' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
     * Kullanıcının erişebileceği tanımlar: sahip + genel paylaşım + kişi/rol paylaşımı.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $userId = (int) $user->id;
        $roleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $query->where(function ($q) use ($userId, $roleIds) {
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
        });
    }

    public function isAccessibleBy(User $user): bool
    {
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

        return count(array_intersect(array_map('intval', $shareRoles), $userRoleIds)) > 0;
    }
}
