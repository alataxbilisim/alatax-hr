<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature suite tek process'te auth throttle (10/dk) birikmesin.
        // AuthThrottleTest kendi içinde yeniden clear edip 429 davranışını doğrular.
        $this->clearAuthRateLimiters();
    }

    /**
     * throttle:auth anahtarları — Laravel ThrottleRequests md5(limiterName.key) kullanır.
     */
    protected function clearAuthRateLimiters(): void
    {
        $ips = array_unique(array_filter([
            '127.0.0.1',
            '::1',
            request()->ip(),
        ]));

        foreach ($ips as $ip) {
            RateLimiter::clear(md5('auth'.$ip));
        }
    }

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
