<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dalga 4 (SON): admin grupları — company_admin soft + permission katmanı.
 *
 * Kritik: UserType::user + Spatie hr_manager → 200 (type-only değil, rol bazlı).
 * company_admin type + Spatie admin rolü → 200; rol yoksa → 403.
 */
class PermissionEnforcementWave4Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    /** @var array<string, array{uri: string, permission: string}> */
    private array $groups = [
        'users' => ['uri' => '/api/v1/users', 'permission' => 'management.users.view'],
        'roles' => ['uri' => '/api/v1/roles', 'permission' => 'management.roles.view'],
        'branches' => ['uri' => '/api/v1/branches', 'permission' => 'management.branches.view'],
        'company' => ['uri' => '/api/v1/company', 'permission' => 'management.company.view'],
        'employees' => ['uri' => '/api/v1/employees', 'permission' => 'employees.list.view'],
        'departments' => ['uri' => '/api/v1/departments', 'permission' => 'employees.departments.view'],
        'custom-fields' => ['uri' => '/api/v1/custom-fields', 'permission' => 'management.custom_fields.view'],
        'workflows' => ['uri' => '/api/v1/workflows', 'permission' => 'management.workflows.view'],
        'api-keys' => ['uri' => '/api/v1/api-keys', 'permission' => 'management.api_keys.view'],
        'webhooks' => ['uri' => '/api/v1/webhooks', 'permission' => 'management.webhooks.view'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'management.users.view',
            'management.roles.view',
            'management.branches.view',
            'management.company.view',
            'management.settings.view',
            'management.custom_fields.view',
            'management.workflows.view',
            'management.api_keys.view',
            'management.webhooks.view',
            'management.*',
            'employees.list.view',
            'employees.departments.view',
            'employees.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function makeUser(UserType $type, ?Company $company = null): User
    {
        $user = User::factory()->create([
            'type' => $type,
            'company_id' => $type === UserType::SuperAdmin ? null : ($company ?? $this->company)->id,
            'is_active' => true,
        ]);

        if ($type === UserType::CompanyAdmin) {
            return $this->assignSpatieAdminRole($user);
        }

        return $user;
    }

    /** Rol atanmamış company_admin — Gate bypass yok, 403 beklenir. */
    private function makeCompanyAdminWithoutRole(?Company $company = null): User
    {
        return User::factory()->create([
            'type' => UserType::CompanyAdmin,
            'company_id' => ($company ?? $this->company)->id,
            'is_active' => true,
        ]);
    }

    private function makeHrManagerUser(?Company $company = null): User
    {
        $user = $this->makeUser(UserType::User, $company);

        $role = Role::findOrCreate('hr_manager', 'sanctum');
        $role->syncPermissions([
            'management.users.view',
            'management.roles.view',
            'management.branches.view',
            'management.company.view',
            'management.custom_fields.view',
            'employees.list.view',
            'employees.departments.view',
            'employees.*',
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_groups_unauthenticated_returns_401(): void
    {
        foreach ($this->groups as $name => $group) {
            $this->assertSame(401, $this->getJson($group['uri'])->status(), "{$name} auth yok");
        }
    }

    public function test_admin_groups_user_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));

        foreach ($this->groups as $name => $group) {
            $this->assertSame(403, $this->getJson($group['uri'])->status(), "{$name} izinsiz user");
        }
    }

    public function test_admin_groups_user_with_hr_manager_role_returns_200(): void
    {
        // Kritik: company_admin TYPE yok — Spatie hr_manager ile erişim
        $hr = $this->makeHrManagerUser();
        Sanctum::actingAs($hr);

        $this->assertSame(UserType::User, $hr->type);
        $this->assertTrue($hr->hasRole('hr_manager'));

        foreach (['users', 'roles', 'branches', 'company', 'employees', 'departments', 'custom-fields'] as $name) {
            $group = $this->groups[$name];
            $this->assertSame(200, $this->getJson($group['uri'])->status(), "{$name} hr_manager");
        }

        // hr_manager'a api-keys / webhooks / workflows YOK → 403 (yanlış 200 bypass kontrolü)
        $this->assertSame(403, $this->getJson('/api/v1/api-keys')->status(), 'api-keys hr_manager engelli');
        $this->assertSame(403, $this->getJson('/api/v1/webhooks')->status(), 'webhooks hr_manager engelli');
        $this->assertSame(403, $this->getJson('/api/v1/workflows')->status(), 'workflows hr_manager engelli');
    }

    public function test_admin_groups_user_with_direct_permission_returns_200(): void
    {
        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('management.users.view');
        Sanctum::actingAs($user);

        $this->assertSame(200, $this->getJson('/api/v1/users')->status());
        $this->assertSame(403, $this->getJson('/api/v1/roles')->status(), 'yalnızca users.view → roles engelli');
    }

    public function test_admin_groups_company_admin_without_admin_role_returns_403(): void
    {
        $admin = $this->makeCompanyAdminWithoutRole();
        $this->assertSame(UserType::CompanyAdmin, $admin->type);
        $this->assertFalse($admin->hasRole('admin'));
        $this->assertCount(0, $admin->getAllPermissions());
        Sanctum::actingAs($admin);

        foreach ($this->groups as $name => $group) {
            $this->assertSame(403, $this->getJson($group['uri'])->status(), "{$name} company_admin rolsüz");
        }
    }

    public function test_admin_groups_company_admin_with_admin_role_returns_200(): void
    {
        $admin = $this->makeUser(UserType::CompanyAdmin);
        $this->assertTrue($admin->hasRole('admin'));
        Sanctum::actingAs($admin);

        foreach ($this->groups as $name => $group) {
            $this->assertSame(200, $this->getJson($group['uri'])->status(), "{$name} company_admin+admin");
        }
    }

    public function test_admin_groups_super_admin_bypass(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::SuperAdmin));

        foreach ($this->groups as $name => $group) {
            $status = $this->getJson($group['uri'])->status();
            // SuperAdmin company_id yok → /company 404 olabilir (controller firma bağlamı ister)
            if ($name === 'company') {
                $this->assertContains($status, [200, 404], 'company super_admin');

                continue;
            }
            $this->assertSame(200, $status, "{$name} super_admin");
        }
    }

    public function test_employees_tenant_isolation(): void
    {
        $ownUser = $this->makeUser(UserType::User, $this->company);
        $otherUser = $this->makeUser(UserType::User, $this->otherCompany);

        $own = Employee::factory()->forUser($ownUser)->create();
        Employee::factory()->forUser($otherUser)->create();

        $viewer = $this->makeUser(UserType::User, $this->company);
        $viewer->assignRole(\Spatie\Permission\Models\Role::findOrCreate('hr_manager', 'sanctum'));
        $viewer->givePermissionTo('employees.list.view');
        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/v1/employees')->assertStatus(200);
        $ids = collect($response->json('data'));
        if (isset($response->json('data')['data'])) {
            $ids = collect($response->json('data.data'))->pluck('id');
        } else {
            $ids = collect($response->json('data'))->pluck('id');
        }

        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids->filter()->unique());
    }

    public function test_permission_middleware_not_skipped_for_plain_user(): void
    {
        // company_admin soft-pass user'ı bırakır; permission katmanı asıl kapı
        Sanctum::actingAs($this->makeUser(UserType::User));
        $this->getJson('/api/v1/users')->assertStatus(403);
        $this->getJson('/api/v1/api-keys')->assertStatus(403);
    }
}
