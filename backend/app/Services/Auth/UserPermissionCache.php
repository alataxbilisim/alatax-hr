<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * D3/E0 — /me permission listesi cache invalidasyonu.
 */
class UserPermissionCache
{
    public static function key(int $userId): string
    {
        return 'auth.user.permissions.'.$userId;
    }

    public static function forgetForUser(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    /**
     * @param  iterable<int|string>  $userIds
     */
    public static function forgetForUsers(iterable $userIds): void
    {
        foreach ($userIds as $id) {
            self::forgetForUser((int) $id);
        }
    }

    public static function forgetForRole(Role $role): void
    {
        $userIds = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->pluck('model_id');

        self::forgetForUsers($userIds);

        // Spatie kendi permission cache'ini de temizle — yoksa getAllPermissions eski kalır
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
