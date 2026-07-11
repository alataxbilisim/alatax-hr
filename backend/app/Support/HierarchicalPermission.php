<?php

namespace App\Support;

/**
 * Hiyerarşik Spatie izin eşleştirici ({module}.{page}.{action}).
 * Frontend matchesPermission ile aynı kurallar: tam eşleşme, page.*, module.*, *.
 */
final class HierarchicalPermission
{
    /**
     * @param  iterable<int, string>  $userPermissions
     */
    public static function matches(iterable $userPermissions, string $ability): bool
    {
        $permissions = [];
        foreach ($userPermissions as $permission) {
            $permissions[] = is_string($permission) ? $permission : (string) $permission;
        }

        if ($permissions === []) {
            return false;
        }

        if (in_array('*', $permissions, true) || in_array($ability, $permissions, true)) {
            return true;
        }

        $parts = explode('.', $ability);
        if (count($parts) < 2) {
            return false;
        }

        $module = $parts[0];
        if (in_array($module.'.*', $permissions, true)) {
            return true;
        }

        if (count($parts) >= 3) {
            $page = $parts[1];
            if (in_array($module.'.'.$page.'.*', $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
