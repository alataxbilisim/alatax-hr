<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\DataScopeService;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FAZ A3 — Branch DataScope.
 */
class BranchDataScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->branchA = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Şube A',
            'code' => 'A',
            'is_active' => true,
        ]);
        $this->branchB = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Şube B',
            'code' => 'B',
            'is_active' => true,
        ]);
    }

    private function makeBranchManager(Branch $branch): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $user->assignRole('branch_manager');
        Employee::factory()->forUser($user)->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    public function test_branch_scope_lists_only_same_branch_employees(): void
    {
        $manager = $this->makeBranchManager($this->branchA);

        $same = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);
        $other = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'status' => 'active',
        ]);
        $nullBranch = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);
        $response = $this->getJson('/api/v1/employees');
        $response->assertOk();
        $rows = $this->employeeListRows($response->json());
        $ids = collect($rows)->pluck('id')->all();

        $this->assertContains($same->id, $ids);
        $this->assertContains($manager->employee->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertNotContains($nullBranch->id, $ids);
    }

    public function test_allows_employee_same_branch_only(): void
    {
        $manager = $this->makeBranchManager($this->branchA);
        $same = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
        ]);
        $other = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
        ]);

        $svc = app(DataScopeService::class);
        $this->assertTrue($svc->allowsEmployee($manager, $same));
        $this->assertFalse($svc->allowsEmployee($manager, $other));
    }

    public function test_null_branch_id_backward_compatible_company_admin_sees_all(): void
    {
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => null,
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);

        Sanctum::actingAs($admin->fresh());
        $rows = $this->employeeListRows(
            $this->getJson('/api/v1/employees?per_page=50')->assertOk()->json()
        );
        $this->assertGreaterThanOrEqual(2, count($rows));
    }

    public function test_tenant_isolation_other_company_branch_invisible(): void
    {
        $otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
        $foreignBranch = Branch::create([
            'company_id' => $otherCompany->id,
            'name' => 'Yabancı',
            'code' => 'X',
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $foreignBranch->id,
            'status' => 'active',
        ]);

        $manager = $this->makeBranchManager($this->branchA);
        Sanctum::actingAs($manager);

        $rows = $this->employeeListRows($this->getJson('/api/v1/employees')->assertOk()->json());
        $companyIds = collect($rows)->pluck('company_id')->unique()->values()->all();

        $this->assertSame([$this->company->id], $companyIds);
    }

    public function test_branch_manager_role_defaults_to_branch_scope(): void
    {
        $role = Role::findByName('branch_manager', 'sanctum');
        $this->assertSame('branch', $role->data_scope);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $user->assignRole('branch_manager');

        $this->assertSame(
            \App\Enums\DataScopeLevel::Branch,
            app(DataScopeService::class)->resolve($user->fresh())
        );
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, mixed>>
     */
    private function employeeListRows(?array $json): array
    {
        $payload = $json['data'] ?? null;
        if (! is_array($payload)) {
            return [];
        }
        if (array_is_list($payload)) {
            return $payload;
        }

        $inner = $payload['data'] ?? [];

        return is_array($inner) ? $inner : [];
    }
}
