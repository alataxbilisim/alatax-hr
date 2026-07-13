<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\PanelAccess;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
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
            'company_id' => $this->company->id,
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
            'company_id' => $this->company->id,
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
            'company_id' => $this->company->id,
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
            'company_id' => $this->company->id,
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
            'company_id' => $this->company->id,
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
}
