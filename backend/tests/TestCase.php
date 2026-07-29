<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /** Testlerin bağlanmasına izin verilen tek veritabanı adı. */
    private const TESTING_DATABASE = 'alatax_hr_testing';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertUsingTestingDatabase();

        // Feature suite tek process'te auth throttle (10/dk) birikmesin.
        // AuthThrottleTest kendi içinde yeniden clear edip 429 davranışını doğrular.
        $this->clearAuthRateLimiters();

        // Spatie + RefreshDatabase: rollback sonrası stale permission cache
        // PermissionDoesNotExist flaky'lerinin kök nedeni — her testte izole et.
        $this->isolatePermissionCache();
    }

    protected function tearDown(): void
    {
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        parent::tearDown();
    }

    /**
     * Test ortamında cache store + Spatie registrar'ı sıfırla.
     * Paylaşılan Redis/database cache testler arası sızıntı yapmasın.
     */
    protected function isolatePermissionCache(): void
    {
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * RefreshDatabase migrate:fresh ÖNCESİ — yanlış DB'de wipe'ı engeller.
     * Not: dönüş tipi yok — Laravel trait imzasıyla uyumlu kalmalı.
     */
    protected function beforeRefreshingDatabase()
    {
        $this->assertUsingTestingDatabase();
    }

    /**
     * Dev DB (alatax_hr) üzerinde test çalıştırmayı gürültülü şekilde reddet.
     */
    protected function assertUsingTestingDatabase(): void
    {
        $name = DB::connection()->getDatabaseName();

        if ($name !== self::TESTING_DATABASE) {
            throw new RuntimeException(
                'Refusing to run tests against non-testing database: '.$name
                .' (expected '.self::TESTING_DATABASE.')'
            );
        }
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findOrCreate('admin', 'sanctum');

        if ($role->data_scope === null) {
            $role->forceFill(['data_scope' => 'company'])->save();
        }

        // Model koleksiyonu ile sync — isim listesinde TOCTOU / cache yarışını azaltır
        $perms = Permission::query()->where('guard_name', 'sanctum')->get();
        if ($perms->isNotEmpty()) {
            $role->syncPermissions($perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }
}
