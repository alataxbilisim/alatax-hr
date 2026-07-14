<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FAZ A6 — Şube bağlam seçici + cross-branch rapor.
 */
class BranchContextTest extends TestCase
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

    private function makeAdmin(): User
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);

        return $this->assignSpatieAdminRole($admin);
    }

    public function test_unauthenticated_context_branches_returns_401(): void
    {
        $this->getJson('/api/v1/context/branches')->assertUnauthorized();
    }

    public function test_branch_manager_context_locked_to_own_branch(): void
    {
        $manager = $this->makeBranchManager($this->branchA);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/context/branches')
            ->assertOk()
            ->assertJsonPath('data.can_select_all', false)
            ->assertJsonPath('data.locked_branch_id', $this->branchA->id)
            ->assertJsonCount(1, 'data.branches');
    }

    public function test_branch_manager_other_branch_header_forbidden(): void
    {
        $manager = $this->makeBranchManager($this->branchA);
        Sanctum::actingAs($manager);

        $this->withHeader('X-Branch-Id', (string) $this->branchB->id)
            ->getJson('/api/v1/employees')
            ->assertForbidden();
    }

    public function test_branch_manager_list_only_own_branch(): void
    {
        $manager = $this->makeBranchManager($this->branchA);
        $same = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);
        $rows = $this->employeeListRows(
            $this->getJson('/api/v1/employees')->assertOk()->json()
        );
        $ids = collect($rows)->pluck('id')->all();

        $this->assertContains($same->id, $ids);
        $this->assertContains($manager->employee->id, $ids);
        $otherId = Employee::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('branch_id', $this->branchB->id)
            ->value('id');
        $this->assertNotNull($otherId);
        $this->assertNotContains($otherId, $ids);
    }

    public function test_admin_can_select_all_and_filter_by_branch(): void
    {
        $admin = $this->makeAdmin();
        $inA = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);
        $inB = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/context/branches')
            ->assertOk()
            ->assertJsonPath('data.can_select_all', true);

        $idsAll = collect($this->employeeListRows(
            $this->withHeader('X-Branch-Id', 'all')
                ->getJson('/api/v1/employees?per_page=50')
                ->assertOk()
                ->json()
        ))->pluck('id')->all();

        $this->assertContains($inA->id, $idsAll);
        $this->assertContains($inB->id, $idsAll);

        $idsA = collect($this->employeeListRows(
            $this->withHeader('X-Branch-Id', (string) $this->branchA->id)
                ->getJson('/api/v1/employees?per_page=50')
                ->assertOk()
                ->json()
        ))->pluck('id')->all();

        $this->assertContains($inA->id, $idsA);
        $this->assertNotContains($inB->id, $idsA);
    }

    public function test_cross_branch_report_admin_ok_branch_manager_forbidden(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/employees/reports/by-branch')
            ->assertOk()
            ->assertJsonPath('success', true);

        $manager = $this->makeBranchManager($this->branchA);
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/employees/reports/by-branch')->assertForbidden();
    }

    public function test_tenant_isolation_context_branches(): void
    {
        $other = Company::factory()->create(['status' => CompanyStatus::Active]);
        Branch::create([
            'company_id' => $other->id,
            'name' => 'Foreign',
            'code' => 'F',
            'is_active' => true,
        ]);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $ids = collect($this->getJson('/api/v1/context/branches')->assertOk()->json('data.branches'))
            ->pluck('id')
            ->all();

        $this->assertContains($this->branchA->id, $ids);
        $this->assertContains($this->branchB->id, $ids);
        $this->assertSame(
            [$this->company->id],
            Branch::whereIn('id', $ids)->pluck('company_id')->unique()->values()->all()
        );
    }

    public function test_hr_manager_has_cross_branch_permission(): void
    {
        $hr = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $hr->assignRole('hr_manager');
        Employee::factory()->forUser($hr)->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
        ]);

        Sanctum::actingAs($hr->fresh());
        $this->getJson('/api/v1/employees/reports/by-branch')->assertOk();
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
