<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FAZ B-3 — Masraf HR yüzü + workflow wire + puantaj HR panosu.
 */
class ExpenseAttendanceHrFaceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private Branch $branchA;

    private Branch $branchB;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->branchA = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'A',
            'code' => 'A',
            'is_active' => true,
        ]);
        $this->branchB = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'B',
            'code' => 'B',
            'is_active' => true,
        ]);

        $this->category = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Yemek',
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);
        Employee::factory()->forUser($admin)->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);

        return $admin->fresh();
    }

    private function userWithRole(string $role, array $extraPerms = []): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $user->assignRole($role);
        if ($extraPerms !== []) {
            $user->givePermissionTo($extraPerms);
        }

        return $user->fresh();
    }

    private function makeClaim(User $owner, string $status = ExpenseClaim::STATUS_SUBMITTED): ExpenseClaim
    {
        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'status' => $status,
            'total_amount' => 100,
        ]);
        ExpenseItem::create([
            'expense_claim_id' => $claim->id,
            'expense_category_id' => $this->category->id,
            'description' => 'Kalem',
            'item_date' => now()->toDateString(),
            'amount' => 100,
        ]);

        return $claim;
    }

    private function createExpenseWorkflow(User $approver): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Masraf onay',
            'entity_type' => ApprovalWorkflow::ENTITY_EXPENSE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'name' => 'HR',
            'step_order' => 1,
            'approver_type' => ApprovalStep::APPROVER_SPECIFIC_USER,
            'specific_user_id' => $approver->id,
            'can_skip' => false,
        ]);

        return $workflow;
    }

    // ——— Masraf HR index / yetki / tenant / branch ———

    public function test_expense_claims_unauthenticated_401(): void
    {
        $this->getJson('/api/v1/expenses/claims')->assertStatus(401);
    }

    public function test_expense_claims_unauthorized_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/expenses/claims')->assertStatus(403);
    }

    public function test_expense_hr_index_happy(): void
    {
        $hr = $this->userWithRole('hr_manager');
        Employee::factory()->forUser($hr)->create(['branch_id' => $this->branchA->id]);
        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($employee)->create(['branch_id' => $this->branchA->id]);
        $claim = $this->makeClaim($employee);

        Sanctum::actingAs($hr);

        $ids = collect($this->getJson('/api/v1/expenses/claims')->assertOk()->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($claim->id));
    }

    public function test_expense_tenant_isolation(): void
    {
        $hr = $this->userWithRole('hr_manager');
        Employee::factory()->forUser($hr)->create(['branch_id' => $this->branchA->id]);

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

        $ids = collect($this->getJson('/api/v1/expenses/claims')->assertOk()->json('data.data'))->pluck('id');
        $this->assertFalse($ids->contains($otherClaim->id));
        $this->getJson("/api/v1/expenses/claims/{$otherClaim->id}")->assertStatus(404);
    }

    public function test_expense_branch_manager_scoped(): void
    {
        $bm = $this->userWithRole('branch_manager');
        Employee::factory()->forUser($bm)->create(['branch_id' => $this->branchA->id]);

        $inBranch = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($inBranch)->create(['branch_id' => $this->branchA->id]);
        $outBranch = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($outBranch)->create(['branch_id' => $this->branchB->id]);

        $own = $this->makeClaim($inBranch);
        $other = $this->makeClaim($outBranch);

        Sanctum::actingAs($bm);

        $ids = collect($this->getJson('/api/v1/expenses/claims')->assertOk()->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($other->id));
        $this->postJson("/api/v1/expenses/claims/{$other->id}/approve")->assertStatus(403);
    }

    // ——— approve → paid + workflow ———

    public function test_expense_approve_then_mark_paid_legacy(): void
    {
        $admin = $this->makeAdmin();
        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($employee)->create(['branch_id' => $this->branchA->id]);
        $claim = $this->makeClaim($employee);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/expenses/claims/{$claim->id}/approve")->assertOk();
        $this->assertSame(ExpenseClaim::STATUS_APPROVED, $claim->fresh()->status);

        $this->postJson("/api/v1/expenses/claims/{$claim->id}/mark-paid", [
            'payment_reference' => 'TRX-1',
            'payment_method' => 'havale',
        ])->assertOk();

        $paid = $claim->fresh();
        $this->assertSame(ExpenseClaim::STATUS_PAID, $paid->status);
        $this->assertSame('TRX-1', $paid->payment_reference);
        $this->assertNotNull($paid->paid_at);
    }

    public function test_expense_submit_without_workflow_stays_submitted(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($employee)->create(['branch_id' => $this->branchA->id]);

        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'status' => ExpenseClaim::STATUS_DRAFT,
            'total_amount' => 50,
        ]);
        ExpenseItem::create([
            'expense_claim_id' => $claim->id,
            'expense_category_id' => $this->category->id,
            'description' => 'Kalem',
            'item_date' => now()->toDateString(),
            'amount' => 50,
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/portal/expenses/{$claim->id}/submit")->assertOk();
        $this->assertSame(ExpenseClaim::STATUS_SUBMITTED, $claim->fresh()->status);
        $this->assertDatabaseMissing('approval_instances', [
            'approvable_id' => $claim->id,
            'approvable_type' => ExpenseClaim::class,
        ]);
    }

    public function test_expense_submit_starts_workflow_when_configured(): void
    {
        $hr = $this->userWithRole('hr_manager');
        Employee::factory()->forUser($hr)->create(['branch_id' => $this->branchA->id]);
        $this->createExpenseWorkflow($hr);

        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($employee)->create(['branch_id' => $this->branchA->id]);

        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'status' => ExpenseClaim::STATUS_DRAFT,
            'total_amount' => 75,
        ]);
        ExpenseItem::create([
            'expense_claim_id' => $claim->id,
            'expense_category_id' => $this->category->id,
            'description' => 'Kalem',
            'item_date' => now()->toDateString(),
            'amount' => 75,
        ]);

        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/portal/expenses/{$claim->id}/submit")->assertOk();

        $this->assertDatabaseHas('approval_instances', [
            'company_id' => $this->company->id,
            'approvable_id' => $claim->id,
            'approvable_type' => ExpenseClaim::class,
            'status' => ApprovalInstance::STATUS_IN_PROGRESS,
        ]);
        $this->assertDatabaseHas('approval_records', [
            'approvable_id' => $claim->id,
            'approver_id' => $hr->id,
            'status' => ApprovalRecord::STATUS_PENDING,
            'is_current' => true,
        ]);

        Sanctum::actingAs($hr);
        $this->postJson("/api/v1/expenses/claims/{$claim->id}/approve")->assertOk();
        $this->assertSame(ExpenseClaim::STATUS_APPROVED, $claim->fresh()->status);
    }

    // ——— Kategori CRUD ———

    public function test_expense_category_crud_happy_and_permission(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/expenses/categories')->assertStatus(403);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/v1/expenses/categories', [
            'name' => 'Ulaşım',
            'code' => 'ULS',
            'requires_receipt' => true,
        ])->assertStatus(201);

        $id = (int) $create->json('data.id');

        $this->putJson("/api/v1/expenses/categories/{$id}", [
            'name' => 'Ulaşım Güncel',
        ])->assertOk();

        $this->assertSame('Ulaşım Güncel', ExpenseCategory::find($id)?->name);

        $this->deleteJson("/api/v1/expenses/categories/{$id}")->assertOk();
        $this->assertNull(ExpenseCategory::find($id));
    }

    public function test_expense_category_tenant_isolation(): void
    {
        $admin = $this->makeAdmin();
        $otherCat = ExpenseCategory::factory()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other',
        ]);

        Sanctum::actingAs($admin);

        $ids = collect($this->getJson('/api/v1/expenses/categories')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($otherCat->id));
        $this->getJson("/api/v1/expenses/categories/{$otherCat->id}")->assertStatus(404);
    }

    // ——— Puantaj ———

    public function test_attendance_hr_board_and_approve(): void
    {
        $hr = $this->userWithRole('hr_manager');
        Employee::factory()->forUser($hr)->create(['branch_id' => $this->branchA->id]);

        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($employee)->create(['branch_id' => $this->branchA->id]);

        $record = AttendanceRecord::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($hr);

        $ids = collect($this->getJson('/api/v1/attendance')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($record->id));

        $this->postJson("/api/v1/attendance/{$record->id}/approve")->assertOk();
        $this->assertTrue($record->fresh()->is_approved);

        $r2 = AttendanceRecord::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'present',
            'is_approved' => false,
        ]);

        $this->postJson('/api/v1/attendance/bulk-approve', ['ids' => [$r2->id]])->assertOk()
            ->assertJsonPath('data.approved_count', 1);
        $this->assertTrue($r2->fresh()->is_approved);
    }

    public function test_attendance_branch_manager_scoped(): void
    {
        $bm = $this->userWithRole('branch_manager');
        Employee::factory()->forUser($bm)->create(['branch_id' => $this->branchA->id]);

        $in = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($in)->create(['branch_id' => $this->branchA->id]);
        $out = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::factory()->forUser($out)->create(['branch_id' => $this->branchB->id]);

        $own = AttendanceRecord::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $in->id,
            'date' => now()->toDateString(),
            'is_approved' => false,
        ]);
        $other = AttendanceRecord::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $out->id,
            'date' => now()->toDateString(),
            'is_approved' => false,
        ]);

        Sanctum::actingAs($bm);

        $ids = collect($this->getJson('/api/v1/attendance')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($other->id));
        $this->postJson("/api/v1/attendance/{$other->id}/approve")->assertStatus(403);
    }

    public function test_attendance_unauthorized_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/attendance')->assertStatus(403);
    }
}
