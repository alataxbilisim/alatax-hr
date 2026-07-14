<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FAZ A4 — Organizasyon şeması 3 mod (people / department / hybrid).
 */
class OrganizationChartTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'employees.organization.view',
            'employees.list.view',
            'employees.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        Role::findOrCreate('admin', 'sanctum');
        Role::findOrCreate('employee', 'sanctum');
        Role::findOrCreate('hr_viewer', 'sanctum');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        $this->admin = $this->admin->fresh();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/employees/organization-chart')
            ->assertStatus(401);
    }

    public function test_unauthorized_role_gets_403(): void
    {
        $plain = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $plain->assignRole('employee');

        Sanctum::actingAs($plain);
        $this->getJson('/api/v1/employees/organization-chart')
            ->assertStatus(403);
    }

    public function test_people_mode_builds_manager_tree(): void
    {
        $ceoUser = User::factory()->create(['company_id' => $this->company->id, 'name' => 'CEO']);
        $mgrUser = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Manager']);
        $empUser = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Employee']);

        $ceo = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $ceoUser->id,
            'manager_id' => null,
            'status' => 'active',
        ]);
        $mgr = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $mgrUser->id,
            'manager_id' => $ceo->id,
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $empUser->id,
            'manager_id' => $mgr->id,
            'status' => 'active',
        ]);

        Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'status' => 'active',
            'manager_id' => null,
        ]);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/v1/employees/organization-chart?mode=people');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'people');

        $tree = $response->json('data.tree');
        $this->assertCount(1, $tree);
        $this->assertSame('employee', $tree[0]['type']);
        $this->assertSame($ceo->id, $tree[0]['employee']['id']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame($mgr->id, $tree[0]['children'][0]['employee']['id']);
        $this->assertCount(1, $tree[0]['children'][0]['children']);
    }

    public function test_department_mode_builds_department_hierarchy(): void
    {
        $root = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Genel Müdürlük',
            'code' => 'GM',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $child = Department::create([
            'company_id' => $this->company->id,
            'name' => 'İK',
            'code' => 'HR',
            'parent_id' => $root->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Department::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Diğer Firma',
            'code' => 'X',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/v1/employees/organization-chart?mode=department');

        $response->assertOk()->assertJsonPath('data.mode', 'department');
        $tree = $response->json('data.tree');
        $this->assertCount(1, $tree);
        $this->assertSame('department', $tree[0]['type']);
        $this->assertSame($root->id, $tree[0]['department']['id']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame($child->id, $tree[0]['children'][0]['department']['id']);
    }

    public function test_hybrid_mode_nests_people_under_departments(): void
    {
        $dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Satış',
            'code' => 'SALES',
            'is_active' => true,
        ]);
        $inDept = Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'status' => 'active',
        ]);
        $unassigned = Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/v1/employees/organization-chart?mode=hybrid');

        $response->assertOk()->assertJsonPath('data.mode', 'hybrid');
        $tree = $response->json('data.tree');

        $sales = collect($tree)->first(
            fn (array $n) => ($n['department']['id'] ?? null) === $dept->id
        );
        $this->assertNotNull($sales);
        $this->assertSame('department', $sales['type']);
        $childIds = collect($sales['children'])->pluck('employee.id')->filter()->all();
        $this->assertContains($inDept->id, $childIds);

        $orphan = collect($tree)->first(
            fn (array $n) => ($n['department']['is_unassigned'] ?? false) === true
        );
        $this->assertNotNull($orphan);
        $orphanIds = collect($orphan['children'])->pluck('employee.id')->filter()->all();
        $this->assertContains($unassigned->id, $orphanIds);
    }

    public function test_invalid_mode_returns_422(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/employees/organization-chart?mode=branches')
            ->assertStatus(422);
    }

    public function test_default_mode_is_people(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/employees/organization-chart')
            ->assertOk()
            ->assertJsonPath('data.mode', 'people');
    }

    public function test_user_with_organization_view_permission_succeeds(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findByName('hr_viewer', 'sanctum');
        $role->givePermissionTo('employees.organization.view');
        if ($role->data_scope === null) {
            $role->forceFill(['data_scope' => 'company'])->save();
        }
        $user->assignRole($role);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/employees/organization-chart?mode=department')
            ->assertOk()
            ->assertJsonPath('data.mode', 'department');
    }
}
