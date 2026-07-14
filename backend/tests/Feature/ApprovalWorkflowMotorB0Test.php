<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Events\ApprovalRequested;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use App\Services\DefaultLeaveApprovalWorkflowService;
use App\Services\WorkflowService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 4B B0: onay motoru bağlama (pilot: izin) + findApprover hiyerarşi.
 */
class ApprovalWorkflowMotorB0Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'leaves.requests.view',
            'leaves.requests.create',
            'leaves.requests.approve',
            'leaves.requests.edit',
            'leaves.requests.delete',
            'leaves.*',
            'approvals.view',
            'approvals.approve',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'leave-management');
        $this->enableModule($this->otherCompany, 'leave-management');

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN-B0',
            'is_active' => true,
            'default_days' => 14,
        ]);

        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($this->company);
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug,
                'is_core' => false,
                'is_active' => true,
            ]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $user->assignRole($role);
        $user->givePermissionTo([
            'leaves.requests.view',
            'leaves.requests.approve',
            'leaves.requests.create',
            'approvals.view',
            'approvals.approve',
        ]);

        return $user;
    }

    public function test_find_approver_resolves_employee_manager_user(): void
    {
        $manager = $this->userWithRole('manager');
        $sub = $this->userWithRole('employee');

        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);

        $workflow = ApprovalWorkflow::query()
            ->where('company_id', $this->company->id)
            ->where('is_default', true)
            ->firstOrFail();

        $step = $workflow->steps()->firstOrFail();
        $this->assertSame(ApprovalStep::APPROVER_DYNAMIC_MANAGER, $step->approver_type);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $sub->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'total_days' => 2,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $leave->setRelation('user', $sub->fresh());
        $approver = $step->findApprover($leave);

        $this->assertNotNull($approver);
        $this->assertSame($manager->id, $approver->id);
    }

    public function test_store_starts_instance_and_notifies_manager(): void
    {
        Event::fake([ApprovalRequested::class]);

        $manager = $this->userWithRole('manager');
        $sub = $this->userWithRole('employee');

        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);

        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $sub->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 14,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        Sanctum::actingAs($sub);

        $start = now()->next(\Carbon\Carbon::MONDAY)->toDateString();
        $end = now()->next(\Carbon\Carbon::MONDAY)->addDay()->toDateString();

        $response = $this->postJson('/api/v1/leaves/requests', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Motor B0',
        ]);

        $response->assertStatus(201);
        $leaveId = (int) $response->json('data.id');

        $this->assertDatabaseHas('approval_instances', [
            'company_id' => $this->company->id,
            'approvable_id' => $leaveId,
            'status' => ApprovalInstance::STATUS_IN_PROGRESS,
        ]);

        $this->assertDatabaseHas('approval_records', [
            'approvable_id' => $leaveId,
            'approver_id' => $manager->id,
            'status' => ApprovalRecord::STATUS_PENDING,
            'is_current' => true,
        ]);

        Event::assertDispatched(ApprovalRequested::class, function (ApprovalRequested $e) use ($manager, $leaveId) {
            return (int) $e->approver->id === $manager->id
                && (int) $e->approvable->id === $leaveId;
        });
    }

    public function test_manager_approve_via_bridge_updates_entity_and_balance(): void
    {
        $manager = $this->userWithRole('manager');
        $sub = $this->userWithRole('employee');

        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);

        $balance = LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $sub->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 14,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $sub->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(14)->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
            'total_days' => 2,
            'reason' => 'approve bridge',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $balance->addPending(2);

        $record = app(WorkflowService::class)->startWorkflow($leave, ['total_days' => 2]);
        $this->assertNotNull($record);
        $this->assertSame($manager->id, (int) $record->approver_id);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")
            ->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
        $this->assertSame(LeaveRequest::WORKFLOW_COMPLETED, $leave->workflow_status);

        $this->assertDatabaseHas('approval_instances', [
            'approvable_id' => $leave->id,
            'status' => ApprovalInstance::STATUS_APPROVED,
        ]);

        $balance->refresh();
        $this->assertEquals(2.0, (float) $balance->used_days);
        $this->assertEquals(0.0, (float) $balance->pending_days);
    }

    public function test_pending_approvals_queue_fed_by_motor(): void
    {
        $manager = $this->userWithRole('manager');
        $sub = $this->userWithRole('employee');

        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $sub->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
            'total_days' => 2,
            'reason' => 'queue',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        app(WorkflowService::class)->startWorkflow($leave);

        Sanctum::actingAs($manager);
        $pending = app(WorkflowService::class)->getPendingApprovalsForUser(
            $manager->id,
            $this->company->id
        );

        $this->assertTrue($pending->contains(fn (ApprovalRecord $r) => (int) $r->approvable_id === $leave->id));
    }

    public function test_tenant_isolation_other_company_instance(): void
    {
        $manager = $this->userWithRole('manager');
        $sub = $this->userWithRole('employee');
        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);

        $otherType = LeaveType::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other',
            'code' => 'OT-B0',
            'is_active' => true,
            'default_days' => 10,
        ]);

        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($this->otherCompany);

        $otherUser = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($otherUser)->create();

        $otherLeave = LeaveRequest::create([
            'company_id' => $this->otherCompany->id,
            'user_id' => $otherUser->id,
            'leave_type_id' => $otherType->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'total_days' => 2,
            'reason' => 'other',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        app(WorkflowService::class)->startWorkflow($otherLeave);

        Sanctum::actingAs($manager);
        $pending = app(WorkflowService::class)->getPendingApprovalsForUser(
            $manager->id,
            $this->company->id
        );

        $this->assertFalse($pending->contains(fn (ApprovalRecord $r) => (int) $r->approvable_id === $otherLeave->id));
    }

    public function test_seed_command_is_idempotent(): void
    {
        $service = app(DefaultLeaveApprovalWorkflowService::class);
        $service->ensureForCompany($this->company);
        $service->ensureForCompany($this->company);

        $count = ApprovalWorkflow::query()
            ->where('company_id', $this->company->id)
            ->where('entity_type', ApprovalWorkflow::ENTITY_LEAVE_REQUEST)
            ->where('is_default', true)
            ->count();

        $this->assertSame(1, $count);
        $this->assertSame(1, ApprovalStep::query()
            ->whereHas('workflow', fn ($q) => $q->where('company_id', $this->company->id)->where('is_default', true))
            ->count());
    }
}
