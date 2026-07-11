<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    /**
     * Spatie 'admin' rolünü ata (mevcut sanctum permission'larını sync eder).
     * Gate company_admin bypass kalkınca testlerde company_admin type için zorunlu.
     */
    protected function assignSpatieAdminRole(User $user): User
    {
        $role = Role::findOrCreate('admin', 'sanctum');

        if ($role->data_scope === null) {
            $role->forceFill(['data_scope' => 'company'])->save();
        }

        $perms = Permission::query()->where('guard_name', 'sanctum')->pluck('name');
        if ($perms->isNotEmpty()) {
            $role->syncPermissions($perms->all());
        }

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }
}
