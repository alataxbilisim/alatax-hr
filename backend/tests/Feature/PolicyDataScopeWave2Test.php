<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\Module;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 2 Dalga 2: Employee + ExpenseClaim + PerformanceReview Policy/DataScope.
 */
class PolicyDataScopeWave2Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'employees.list.view',
            'employees.list.edit',
            'employees.list.delete',
            'employees.list.create',
            'employees.*',
            'expenses.claims.view',
            'expenses.claims.approve',
            'expenses.*',
            'performance.reviews.view',
            'performance.reviews.edit',
            'performance.reviews.approve',
            'performance.reviews.create',
            'performance.reviews.delete',
            'performance.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'performance');
        $this->enableModule($this->otherCompany, 'performance');
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_core' => false, 'is_active' => true]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);
    }

    private function userWithRole(string $role, array $permissions = []): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $user->assignRole($role);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    private function extractIds($response): \Illuminate\Support\Collection
    {
        $data = $response->json('data');
        if (is_array($data) && isset($data['data'])) {
            return collect($data['data'])->pluck('id');
        }

        return collect($data)->pluck('id');
    }

    // ——— Employee ———

    public function test_employee_own_sees_self_not_others(): void
    {
        $actor = $this->userWithRole('employee', ['employees.list.view']);
        $other = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $ownEmp = Employee::factory()->forUser($actor)->create();
        $otherEmp = Employee::factory()->forUser($other)->create();

        Sanctum::actingAs($actor);

        $this->getJson("/api/v1/employees/{$ownEmp->id}")->assertStatus(200);
        $this->getJson("/api/v1/employees/{$otherEmp->id}")->assertStatus(403);

        $list = $this->getJson('/api/v1/employees')->assertStatus(200);
        $ids = $this->extractIds($list);
        $this->assertTrue($ids->contains($ownEmp->id));
        $this->assertFalse($ids->contains($otherEmp->id));
    }

    public function test_employee_manager_sees_team_not_other_team(): void
    {
        $manager = $this->userWithRole('manager', ['employees.list.view', 'employees.list.edit']);
        $sub = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $other = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $managerEmp = Employee::factory()->forUser($manager)->create();
        $subEmp = Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);
        $otherEmp = Employee::factory()->forUser($other)->create();

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$subEmp->id}")->assertStatus(200);
        $this->getJson("/api/v1/employees/{$otherEmp->id}")->assertStatus(403);

        // Manager team ile görür ama update yapamaz (İK kapsamı)
        $this->putJson("/api/v1/employees/{$subEmp->id}", ['title' => 'X'])->assertStatus(403);

        $list = $this->getJson('/api/v1/employees')->assertStatus(200);
        $ids = $this->extractIds($list);
        $this->assertTrue($ids->contains($managerEmp->id));
        $this->assertTrue($ids->contains($subEmp->id));
        $this->assertFalse($ids->contains($otherEmp->id));
    }

    public function test_employee_hr_manager_sees_and_updates_all(): void
    {
        $hr = $this->userWithRole('hr_manager', [
            'employees.list.view',
            'employees.list.edit',
        ]);
        $a = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $emp = Employee::factory()->forUser($a)->create();

        Sanctum::actingAs($hr);

        $this->getJson("/api/v1/employees/{$emp->id}")->assertStatus(200);
        $this->putJson("/api/v1/employees/{$emp->id}", ['title' => 'Updated'])->assertStatus(200);
    }

    public function test_employee_tenant_isolation(): void
    {
        $hr = $this->userWithRole('hr_manager', ['employees.list.view']);
        $otherUser = User::factory()->create(['company_id' => $this->otherCompany->id, 'type' => UserType::User]);
        $otherEmp = Employee::factory()->forUser($otherUser)->create();

        Sanctum::actingAs($hr);

        $ids = $this->extractIds($this->getJson('/api/v1/employees')->assertStatus(200));
        $this->assertFalse($ids->contains($otherEmp->id));
        $this->getJson("/api/v1/employees/{$otherEmp->id}")->assertStatus(404);
    }

    // ——— ExpenseClaim ———

    public function test_expense_manager_approves_team_not_other_team(): void
    {
        $manager = $this->userWithRole('manager', [
            'expenses.claims.view',
            'expenses.claims.approve',
        ]);
        $sub = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $other = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);
        Employee::factory()->forUser($other)->create();

        $teamClaim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $sub->id,
            'status' => ExpenseClaim::STATUS_SUBMITTED,
        ]);
        $otherClaim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $other->id,
            'status' => ExpenseClaim::STATUS_SUBMITTED,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/expenses/claims/{$teamClaim->id}")->assertStatus(200);
        $this->postJson("/api/v1/expenses/claims/{$teamClaim->id}/approve")->assertStatus(200);

        $this->getJson("/api/v1/expenses/claims/{$otherClaim->id}")->assertStatus(403);
        $this->postJson("/api/v1/expenses/claims/{$otherClaim->id}/approve")->assertStatus(403);

        $ids = $this->extractIds($this->getJson('/api/v1/expenses/claims')->assertStatus(200));
        $this->assertTrue($ids->contains($teamClaim->id));
        $this->assertFalse($ids->contains($otherClaim->id));
    }

    public function test_expense_hr_manager_approves_all(): void
    {
        $hr = $this->userWithRole('hr_manager', [
            'expenses.claims.view',
            'expenses.claims.approve',
        ]);
        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'status' => ExpenseClaim::STATUS_SUBMITTED,
        ]);

        Sanctum::actingAs($hr);

        $this->postJson("/api/v1/expenses/claims/{$claim->id}/approve")->assertStatus(200);
    }

    public function test_expense_permission_alone_cannot_approve_legacy_closed(): void
    {
        $approver = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $approver->givePermissionTo(['expenses.claims.view', 'expenses.claims.approve']);

        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'status' => ExpenseClaim::STATUS_SUBMITTED,
        ]);

        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/expenses/claims/{$claim->id}/approve")->assertStatus(403);
    }

    public function test_expense_owner_cannot_update_approved(): void
    {
        $owner = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($owner)->create();

        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'status' => ExpenseClaim::STATUS_APPROVED,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/portal/expenses/{$claim->id}", ['title' => 'Hack'])->assertStatus(403);
    }

    public function test_expense_tenant_isolation(): void
    {
        $hr = $this->userWithRole('hr_manager', ['expenses.claims.view']);
        $otherUser = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::User,
        ]);
        $otherClaim = ExpenseClaim::factory()->create([
            'company_id' => $this->otherCompany->id,
            'user_id' => $otherUser->id,
            'status' => ExpenseClaim::STATUS_SUBMITTED,
        ]);

        Sanctum::actingAs($hr);

        $ids = $this->extractIds($this->getJson('/api/v1/expenses/claims')->assertStatus(200));
        $this->assertFalse($ids->contains($otherClaim->id));
        $this->getJson("/api/v1/expenses/claims/{$otherClaim->id}")->assertStatus(404);
    }

    // ——— PerformanceReview ———

    public function test_performance_reviewer_and_reviewee_can_view(): void
    {
        $reviewer = $this->userWithRole('manager', [
            'performance.reviews.view',
            'performance.reviews.edit',
        ]);
        $reviewee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $stranger = $this->userWithRole('employee', ['performance.reviews.view']);

        Employee::factory()->forUser($reviewer)->create();
        Employee::factory()->forUser($reviewee)->create();
        Employee::factory()->forUser($stranger)->create();

        $period = PerformancePeriod::create([
            'company_id' => $this->company->id,
            'name' => '2026 H1',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        $review = PerformanceReview::create([
            'company_id' => $this->company->id,
            'period_id' => $period->id,
            'employee_id' => $reviewee->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'draft',
        ]);

        Sanctum::actingAs($reviewer);
        $this->getJson("/api/v1/performance/reviews/{$review->id}")->assertStatus(200);
        $this->putJson("/api/v1/performance/reviews/{$review->id}", [
            'strengths' => 'İyi iş',
        ])->assertStatus(200);

        Sanctum::actingAs($reviewee->fresh());
        $reviewee->givePermissionTo('performance.reviews.view');
        Sanctum::actingAs($reviewee);
        $this->getJson("/api/v1/performance/reviews/{$review->id}")->assertStatus(200);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/performance/reviews/{$review->id}")->assertStatus(403);
    }

    public function test_performance_manager_cannot_approve_other_team(): void
    {
        $managerA = $this->userWithRole('manager', [
            'performance.reviews.view',
            'performance.reviews.approve',
        ]);
        $managerB = $this->userWithRole('manager', [
            'performance.reviews.view',
            'performance.reviews.approve',
        ]);
        $subB = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        Employee::factory()->forUser($managerA)->create();
        $empB = Employee::factory()->forUser($managerB)->create();
        Employee::factory()->forUser($subB)->create(['manager_id' => $empB->id]);

        $period = PerformancePeriod::create([
            'company_id' => $this->company->id,
            'name' => '2026 H2',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        $review = PerformanceReview::create([
            'company_id' => $this->company->id,
            'period_id' => $period->id,
            'employee_id' => $subB->id,
            'reviewer_id' => $managerB->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($managerA);

        $ids = $this->extractIds($this->getJson('/api/v1/performance/reviews')->assertStatus(200));
        $this->assertFalse($ids->contains($review->id));

        $this->getJson("/api/v1/performance/reviews/{$review->id}")->assertStatus(403);
        $this->postJson("/api/v1/performance/reviews/{$review->id}/approve")->assertStatus(403);
    }

    public function test_performance_hr_manager_approves_all(): void
    {
        $hr = $this->userWithRole('hr_manager', [
            'performance.reviews.view',
            'performance.reviews.approve',
        ]);
        $reviewee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $reviewer = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $period = PerformancePeriod::create([
            'company_id' => $this->company->id,
            'name' => '2026 Q1',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        $review = PerformanceReview::create([
            'company_id' => $this->company->id,
            'period_id' => $period->id,
            'employee_id' => $reviewee->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($hr);

        $this->postJson("/api/v1/performance/reviews/{$review->id}/approve")->assertStatus(200);
    }

    public function test_performance_company_admin_with_admin_role(): void
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin);
        $reviewee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $reviewer = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $period = PerformancePeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Admin Period',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        $review = PerformanceReview::create([
            'company_id' => $this->company->id,
            'period_id' => $period->id,
            'employee_id' => $reviewee->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($admin->fresh());

        $this->getJson("/api/v1/performance/reviews/{$review->id}")->assertStatus(200);
        $this->postJson("/api/v1/performance/reviews/{$review->id}/approve")->assertStatus(200);
    }
}
