<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * company_admin + Spatie admin — Gate type bypass OLMADAN yetki kanıtı.
 */
class CompanyAdminRoleGuaranteeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function companyAdminWithAdminRole(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $user->assignRole(Role::findByName('admin', 'sanctum'));

        return $user->fresh();
    }

    public function test_company_admin_has_admin_role_and_all_key_permissions_via_role(): void
    {
        $user = $this->companyAdminWithAdminRole();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->can('management.users.view'));
        $this->assertTrue($user->can('management.roles.view'));
        $this->assertTrue($user->can('employees.list.view'));
        $this->assertTrue($user->can('employees.salary.view'));
        $this->assertTrue($user->can('management.api_keys.view'));
        $this->assertTrue($user->can('management.webhooks.view'));
        $this->assertTrue($user->can('management.workflows.view'));

        $adminRole = Role::findByName('admin', 'sanctum');
        $this->assertSame('company', $adminRole->data_scope ?? config('data-scope.defaults.admin'));
        $this->assertSame('company', config('data-scope.defaults.admin'));
    }

    public function test_company_admin_with_admin_role_accesses_admin_endpoints(): void
    {
        $user = $this->companyAdminWithAdminRole();
        Sanctum::actingAs($user);

        foreach ([
            '/api/v1/users',
            '/api/v1/roles',
            '/api/v1/employees',
            '/api/v1/departments',
            '/api/v1/company',
            '/api/v1/branches',
            '/api/v1/api-keys',
            '/api/v1/webhooks',
            '/api/v1/workflows',
        ] as $uri) {
            $this->assertSame(200, $this->getJson($uri)->status(), $uri);
        }
    }

    public function test_permissions_come_from_role_not_type_bypass(): void
    {
        $user = $this->companyAdminWithAdminRole();

        $viaRole = $user->getPermissionsViaRoles()->pluck('name');
        $this->assertTrue($viaRole->contains('employees.salary.view'));
        $this->assertTrue($viaRole->contains('management.users.view'));
        $this->assertGreaterThan(50, $viaRole->count());

        // Type tek başına yetmez — rol yoksa Gate false
        $rolsuz = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assertFalse(Gate::forUser($rolsuz)->allows('management.users.view'));
    }

    public function test_gate_allows_when_admin_role_present(): void
    {
        $user = $this->companyAdminWithAdminRole();

        $this->assertTrue(Gate::forUser($user)->allows('employees.salary.view'));
        $this->assertTrue(Gate::forUser($user)->allows('management.audit_logs.view'));
    }

    public function test_company_admin_sees_salary_via_admin_role(): void
    {
        $admin = $this->companyAdminWithAdminRole();
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'gross_salary' => 50000,
            'net_salary' => 40000,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/employees/{$employee->id}");
        $response->assertStatus(200);
        $data = $response->json('data.employee') ?? $response->json('data');
        $this->assertArrayHasKey('gross_salary', $data);
        $this->assertEquals(50000, (float) $data['gross_salary']);
    }

    public function test_company_admin_cannot_see_other_company_employees(): void
    {
        $admin = $this->companyAdminWithAdminRole();
        $otherUser = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::User,
        ]);
        $otherEmployee = Employee::factory()->forUser($otherUser)->create([
            'company_id' => $this->otherCompany->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/employees/{$otherEmployee->id}")->assertStatus(404);
    }

    public function test_super_admin_still_bypasses_gate(): void
    {
        $super = User::factory()->superAdmin()->create();
        $this->assertTrue(Gate::forUser($super)->allows('management.users.view'));
        $this->assertTrue(Gate::forUser($super)->allows('employees.salary.view'));
    }
}
