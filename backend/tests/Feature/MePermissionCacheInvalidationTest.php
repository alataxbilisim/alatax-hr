<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\UserPermissionCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * E0 ADIM 4 — /me permission cache anında temizlenir.
 */
class MePermissionCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'leaves.requests.view',
            'employees.list.view',
            'management.roles.view',
            'management.roles.edit',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    public function test_role_permission_revoke_via_api_clears_me_cache_immediately(): void
    {
        $role = Role::create(['name' => 'e0_cache_role', 'guard_name' => 'sanctum']);
        $role->syncPermissions(['leaves.requests.view', 'employees.list.view']);

        $user = User::factory()->create([
            'type' => UserType::User,
            'home_company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        Sanctum::actingAs($user);
        $me1 = $this->getJson('/api/v1/auth/me')->assertOk();
        $perms1 = $me1->json('data.user.permissions');
        $this->assertIsArray($perms1);
        $this->assertContains('leaves.requests.view', $perms1);
        $this->assertTrue(Cache::has(UserPermissionCache::key((int) $user->id)));

        $admin = User::factory()->create([
            'type' => UserType::CompanyAdmin,
            'home_company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin);

        $update = $this->putJson('/api/v1/roles/'.$role->id, [
            'permissions' => ['employees.list.view'],
        ]);
        $update->assertOk();

        $role->refresh();
        $this->assertFalse(
            $role->permissions()->where('name', 'leaves.requests.view')->exists(),
            'Rol hâlâ leaves.requests.view taşıyor; API sync başarısız'
        );
        $this->assertFalse(
            Cache::has(UserPermissionCache::key((int) $user->id)),
            'Kullanıcı permission cache temizlenmemiş'
        );

        // Kullanıcı modelini taze yükle (Spatie relation cache)
        $user = User::query()->findOrFail($user->id);
        Sanctum::actingAs($user);
        $me2 = $this->getJson('/api/v1/auth/me')->assertOk();
        $perms2 = $me2->json('data.user.permissions');
        $this->assertIsArray($perms2);
        $this->assertNotContains('leaves.requests.view', $perms2);
        $this->assertContains('employees.list.view', $perms2);
    }

    public function test_user_deactivation_clears_permission_cache(): void
    {
        $user = User::factory()->create([
            'type' => UserType::User,
            'home_company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $user->givePermissionTo('leaves.requests.view');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->assertTrue(Cache::has(UserPermissionCache::key((int) $user->id)));

        $user->update(['is_active' => false]);
        $this->assertFalse(Cache::has(UserPermissionCache::key((int) $user->id)));
    }
}
