<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\PanelAccess;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel erişimi (izin tabanlı) + /users filtresi + company login engeli.
 */
class PanelAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function portalOnlyEmployee(): User
    {
        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'email' => 'portal.only@test.local',
            'password' => 'Password123!',
        ]);
        Employee::factory()->forUser($user)->create(['status' => 'active']);
        $user->assignRole(Role::findByName('employee', 'sanctum'));

        return $user->fresh();
    }

    private function hrWithPortal(): User
    {
        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'email' => 'hr.dual@test.local',
            'password' => 'Password123!',
        ]);
        Employee::factory()->forUser($user)->create(['status' => 'active']);
        $user->assignRole(Role::findByName('hr_manager', 'sanctum'));

        return $user->fresh();
    }

    public function test_panel_access_helper_portal_only_false_hr_true(): void
    {
        $portal = $this->portalOnlyEmployee();
        $hr = $this->hrWithPortal();
        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);

        $this->assertFalse(PanelAccess::has($portal));
        $this->assertTrue(PanelAccess::has($hr));
        $this->assertTrue(PanelAccess::has($admin->fresh()));
    }

    public function test_company_login_rejects_portal_only_employee(): void
    {
        $portal = $this->portalOnlyEmployee();

        $this->postJson('/api/v1/auth/login', [
            'email' => $portal->email,
            'password' => 'Password123!',
        ])
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'panel_access_denied')
            ->assertJsonStructure(['errors' => ['portal_url']]);
    }

    public function test_portal_login_allows_portal_only_employee(): void
    {
        $portal = $this->portalOnlyEmployee();

        $this->postJson('/api/v1/auth/login', [
            'email' => $portal->email,
            'password' => 'Password123!',
            'portal_login' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.type', 'portal');
    }

    public function test_company_login_allows_hr_and_admin(): void
    {
        $hr = $this->hrWithPortal();

        $this->postJson('/api/v1/auth/login', [
            'email' => $hr->email,
            'password' => 'Password123!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'email' => 'admin.panel@test.local',
            'password' => 'Password123!',
        ]);
        $this->assignSpatieAdminRole($admin);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertStatus(200);
    }

    public function test_users_index_hides_portal_only_shows_hr(): void
    {
        $portal = $this->portalOnlyEmployee();
        $hr = $this->hrWithPortal();
        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'email' => 'list.admin@test.local',
        ]);
        $this->assignSpatieAdminRole($admin);

        Sanctum::actingAs($admin->fresh());

        $response = $this->getJson('/api/v1/users?per_page=50');
        $response->assertStatus(200);

        $ids = collect($response->json('data.data') ?? $response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($portal->id), 'portal-only users listesinde olmamalı');
        $this->assertTrue($ids->contains($hr->id), 'İK (çift erişim) listede olmalı');
        $this->assertTrue($ids->contains($admin->id), 'admin listede olmalı');
    }

    public function test_portal_candidates_lists_portal_only_not_hr(): void
    {
        $portal = $this->portalOnlyEmployee();
        $hr = $this->hrWithPortal();
        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/v1/users/portal-candidates')->assertOk();
        $ids = collect($res->json('data.data') ?? $res->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($portal->id));
        $this->assertFalse($ids->contains($hr->id));
    }

    public function test_grant_panel_access_assigns_role_and_appears_in_users(): void
    {
        $portal = $this->portalOnlyEmployee();
        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/users/{$portal->id}/panel-access", [
            'role' => 'hr_specialist',
        ])->assertOk()
            ->assertJsonPath('data.panel_access', true);

        $portal->refresh();
        $this->assertTrue($portal->hasRole('hr_specialist'));
        $this->assertTrue($portal->hasRole('employee'));
        $this->assertTrue(PanelAccess::has($portal));

        $list = $this->getJson('/api/v1/users?per_page=50')->assertOk();
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($portal->id));

        // Company login artık mümkün
        auth()->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'portal.only@test.local',
            'password' => 'Password123!',
        ])->assertOk();
    }

    public function test_revoke_panel_access_returns_to_portal_only(): void
    {
        $hr = $this->hrWithPortal();
        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'email' => 'admin.revoke@test.local',
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/users/{$hr->id}/panel-access")
            ->assertOk()
            ->assertJsonPath('data.panel_access', false);

        $hr->refresh();
        $this->assertTrue($hr->hasRole('employee'));
        $this->assertFalse($hr->hasRole('hr_manager'));
        $this->assertFalse(PanelAccess::has($hr));

        $list = $this->getJson('/api/v1/users?per_page=50')->assertOk();
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($hr->id));
    }

    public function test_grant_panel_access_requires_edit_permission(): void
    {
        $portal = $this->portalOnlyEmployee();
        $viewer = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $viewer->givePermissionTo('management.users.view');
        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/users/{$portal->id}/panel-access", [
            'role' => 'hr_specialist',
        ])->assertForbidden();
    }

    /**
     * Tur7/8 — dar yetkili custom rol panel_access=true → listede + login.
     * portal-only (employee) listede görünmez kalır.
     */
    public function test_limited_custom_role_stays_in_users_list_and_can_login(): void
    {
        $perm = \Spatie\Permission\Models\Permission::findOrCreate('employees.list.view', 'sanctum');
        $role = Role::findOrCreate('test_limited', 'sanctum');
        $role->forceFill(['panel_access' => true])->save();
        $role->syncPermissions([$perm]);

        $limited = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'email' => 'limited.panel@test.local',
            'password' => 'Password123!',
        ]);
        $limited->assignRole($role);
        Employee::factory()->forUser($limited)->create(['status' => 'active']);

        $this->assertTrue(PanelAccess::has($limited->fresh()));

        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin->fresh());

        $list = $this->getJson('/api/v1/users?per_page=50')->assertOk();
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($limited->id), 'dar yetkili custom rol /users listesinde olmalı');

        $portal = $this->portalOnlyEmployee();
        $this->assertFalse($ids->contains($portal->id), 'portal-only hâlâ listede olmamalı');

        auth()->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'limited.panel@test.local',
            'password' => 'Password123!',
        ])->assertOk();

        Sanctum::actingAs($limited->fresh());
        $this->getJson('/api/v1/roles')->assertForbidden();
    }

    /**
     * Tur8 — adı employee olmayan ama panel_access=false + yalnız portal-self izinler
     * → panel yok, /users'da yok (rol adına güvenilmez).
     */
    public function test_portal_like_named_role_without_panel_access_flag_is_portal_only(): void
    {
        $perm = \Spatie\Permission\Models\Permission::findOrCreate('employees.list.view', 'sanctum');
        $role = Role::create([
            'name' => 'Otel Personeli',
            'guard_name' => 'sanctum',
            'panel_access' => false,
        ]);
        $role->syncPermissions([$perm]);

        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'email' => 'otel.personel@test.local',
            'password' => 'Password123!',
        ]);
        $user->assignRole($role);
        Employee::factory()->forUser($user)->create(['status' => 'active']);

        $this->assertFalse(PanelAccess::has($user->fresh()));

        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin->fresh());

        $list = $this->getJson('/api/v1/users?per_page=50')->assertOk();
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($user->id));

        auth()->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'otel.personel@test.local',
            'password' => 'Password123!',
        ])
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'panel_access_denied');
    }
}
