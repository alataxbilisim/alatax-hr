<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FAZ B-1 — Bakiye manuel atama + izin iptal + portal types drift.
 */
class LeaveFlowRepairTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private LeaveType $leaveType;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->enableLeaveModule($this->company);

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

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Yıllık',
            'code' => 'YIL',
            'is_active' => true,
            'default_days' => 14,
        ]);
    }

    private function enableLeaveModule(Company $company): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'leave-management'],
            ['name' => 'İzin', 'is_core' => false, 'is_active' => true]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
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

    private function makeEmployeeUser(Branch $branch, string $role = 'employee'): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $user->assignRole($role);
        Employee::factory()->forUser($user)->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    private function makeBalance(User $user, float $total = 14, float $pending = 0): LeaveBalance
    {
        return LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => $total,
            'used_days' => 0,
            'pending_days' => $pending,
            'carried_over' => 0,
        ]);
    }

    public function test_balance_update_unauthenticated_401(): void
    {
        $admin = $this->makeAdmin();
        $balance = $this->makeBalance($admin);

        $this->putJson("/api/v1/leaves/balance/{$balance->id}", [
            'total_days' => 20,
            'reason' => 'Düzeltme',
        ])->assertUnauthorized();
    }

    public function test_balance_update_authorized_happy_and_audit(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeEmployeeUser($this->branchA);
        $balance = $this->makeBalance($target, 14);

        Sanctum::actingAs($admin);
        $res = $this->putJson("/api/v1/leaves/balance/{$balance->id}", [
            'total_days' => 20,
            'carried_over' => 2,
            'reason' => 'Manuel hakediş düzeltmesi',
        ])->assertOk();

        $this->assertEquals(20, (float) $res->json('data.total_days'));

        $this->assertDatabaseHas('leave_balances', [
            'id' => $balance->id,
            'total_days' => 20,
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('action', 'leave_balance_manual_update')
                ->where('model_id', $balance->id)
                ->exists()
        );
    }

    public function test_balance_update_unauthorized_role_403(): void
    {
        $employee = $this->makeEmployeeUser($this->branchA);
        $balance = $this->makeBalance($employee);

        Sanctum::actingAs($employee);
        $this->putJson("/api/v1/leaves/balance/{$balance->id}", [
            'total_days' => 99,
            'reason' => 'Yetkisiz deneme',
        ])->assertForbidden();
    }

    public function test_balance_update_other_tenant_403(): void
    {
        $admin = $this->makeAdmin();
        $otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->enableLeaveModule($otherCompany);
        $otherUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'type' => UserType::User,
        ]);
        $otherType = LeaveType::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other',
            'code' => 'OT',
            'is_active' => true,
            'default_days' => 10,
        ]);
        $foreignBalance = LeaveBalance::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'leave_type_id' => $otherType->id,
            'year' => now()->year,
            'total_days' => 10,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/leaves/balance/{$foreignBalance->id}", [
            'total_days' => 1,
            'reason' => 'Tenant aşımı',
        ])->assertNotFound(); // BelongsToCompany scope → 404
    }

    public function test_balance_update_branch_manager_other_branch_403(): void
    {
        $bm = $this->makeEmployeeUser($this->branchA, 'branch_manager');
        $other = $this->makeEmployeeUser($this->branchB);
        $balance = $this->makeBalance($other);

        Sanctum::actingAs($bm);
        $this->withHeader('X-Branch-Id', (string) $this->branchA->id)
            ->putJson("/api/v1/leaves/balance/{$balance->id}", [
                'total_days' => 5,
                'reason' => 'Başka şube',
            ])
            ->assertForbidden();
    }

    public function test_bulk_update_happy_and_partial_error(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeEmployeeUser($this->branchA);

        $otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
        $foreignType = LeaveType::create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign',
            'code' => 'FR',
            'is_active' => true,
            'default_days' => 5,
        ]);

        Sanctum::actingAs($admin);
        $partial = $this->postJson('/api/v1/leaves/balance/bulk', [
            'user_id' => $target->id,
            'year' => now()->year,
            'reason' => 'Toplu atama kısmi',
            'balances' => [
                ['leave_type_id' => $this->leaveType->id, 'total_days' => 18],
                ['leave_type_id' => $foreignType->id, 'total_days' => 1],
            ],
        ])->assertOk();

        $this->assertNotEmpty($partial->json('data.updated_ids'));
        $this->assertNotEmpty($partial->json('data.errors'));

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $target->id,
            'leave_type_id' => $this->leaveType->id,
            'total_days' => 18,
        ]);
    }

    public function test_cancel_pending_restores_pending_days(): void
    {
        $owner = $this->makeEmployeeUser($this->branchA);
        $balance = $this->makeBalance($owner, 14, 3);

        $request = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'total_days' => 3,
            'status' => LeaveRequestStatus::Pending,
            'reason' => 'Test',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/leaves/requests/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->pending_days);
    }

    public function test_cancel_approved_rejected_for_owner(): void
    {
        $owner = $this->makeEmployeeUser($this->branchA);
        $request = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'total_days' => 2,
            'status' => LeaveRequestStatus::Approved,
            'reason' => 'Test',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/leaves/requests/{$request->id}/cancel")
            ->assertForbidden();
    }

    public function test_admin_cancel_approved_restores_used_days(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeEmployeeUser($this->branchA);
        $balance = $this->makeBalance($owner, 14, 0);
        $balance->update(['used_days' => 2]);

        $request = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'total_days' => 2,
            'status' => LeaveRequestStatus::Approved,
            'reason' => 'Test',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/leaves/requests/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->used_days);
    }

    public function test_cancel_pending_closes_approval_instance(): void
    {
        $owner = $this->makeEmployeeUser($this->branchA);
        $admin = $this->makeAdmin();
        $this->makeBalance($owner, 14, 2);

        $request = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(8)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
            'total_days' => 2,
            'status' => LeaveRequestStatus::Pending,
            'reason' => 'Workflow iptal',
        ]);

        $workflow = \App\Models\ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'İzin WF',
            'entity_type' => \App\Models\ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);
        $step = \App\Models\ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'İK',
            'approver_type' => \App\Models\ApprovalStep::APPROVER_USER,
            'specific_user_id' => $admin->id,
            'is_required' => true,
            'escalation_days' => 3,
        ]);

        $instance = \App\Models\ApprovalInstance::create([
            'company_id' => $this->company->id,
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $request->id,
            'current_step' => 1,
            'status' => \App\Models\ApprovalInstance::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        \App\Models\ApprovalRecord::create([
            'company_id' => $this->company->id,
            'approval_instance_id' => $instance->id,
            'approval_workflow_id' => $workflow->id,
            'approval_step_id' => $step->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $request->id,
            'approver_id' => $admin->id,
            'status' => \App\Models\ApprovalRecord::STATUS_PENDING,
            'step_order' => 1,
            'is_current' => true,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/leaves/requests/{$request->id}/cancel")->assertOk();

        $this->assertSame(
            \App\Models\ApprovalInstance::STATUS_CANCELLED,
            $instance->fresh()->status
        );
        $this->assertSame(
            \App\Models\ApprovalRecord::STATUS_SKIPPED,
            \App\Models\ApprovalRecord::query()
                ->where('approvable_id', $request->id)
                ->first()
                ?->status
        );
    }

    public function test_cancel_other_users_request_403_for_employee(): void
    {
        $owner = $this->makeEmployeeUser($this->branchA);
        $stranger = $this->makeEmployeeUser($this->branchA);
        $request = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'total_days' => 2,
            'status' => LeaveRequestStatus::Pending,
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/leaves/requests/{$request->id}/cancel")
            ->assertForbidden();
    }

    public function test_portal_types_return_default_days(): void
    {
        $user = $this->makeEmployeeUser($this->branchA);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/portal/leaves/types')->assertOk();
        $first = $res->json('data.0');
        $this->assertIsArray($first);
        $this->assertSame(14, (int) $first['default_days']);
        $this->assertSame(14, (int) $first['default_limit']);
        $this->assertSame('day', $first['unit']);
        $this->assertArrayNotHasKey('unit_column_from_db', $first);
    }

    public function test_admin_can_cancel_subordinate_pending(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeEmployeeUser($this->branchA);
        $this->makeBalance($owner, 14, 2);
        $request = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'total_days' => 2,
            'status' => LeaveRequestStatus::Pending,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/leaves/requests/{$request->id}/cancel")
            ->assertOk();
    }
}
