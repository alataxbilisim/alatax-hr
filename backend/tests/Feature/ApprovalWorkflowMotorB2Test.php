<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalDelegation;
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
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 4B B2: dinamik onaycılar + hr fallback + vekalet.
 */
class ApprovalWorkflowMotorB2Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

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
            'leaves.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $module = Module::firstOrCreate(
            ['slug' => 'leave-management'],
            ['name' => 'leave-management', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual B2',
            'code' => 'AN-B2',
            'is_active' => true,
            'default_days' => 20,
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
            'leaves.requests.create',
            'leaves.requests.approve',
            'leaves.requests.edit',
        ]);

        return $user;
    }

    /**
     * employee → manager → skip (üst) hiyerarşisi + 2 adımlı dinamik akış.
     */
    public function test_three_level_hierarchy_two_step_dynamic_flow(): void
    {
        $skip = $this->userWithRole('manager');
        $manager = $this->userWithRole('manager');
        $employee = $this->userWithRole('employee');

        $skipEmp = Employee::factory()->forUser($skip)->create();
        $managerEmp = Employee::factory()->forUser($manager)->create(['manager_id' => $skipEmp->id]);
        Employee::factory()->forUser($employee)->create(['manager_id' => $managerEmp->id]);

        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Dinamik 2 adım',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Direkt yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER,
            'is_required' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'Üst yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_SKIP_MANAGER,
            'is_required' => true,
        ]);

        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 20,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(40)->toDateString(),
            'end_date' => now()->addDays(41)->toDateString(),
            'total_days' => 2,
            'reason' => 'B2 hierarchy',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveBalance::where('user_id', $employee->id)->first()?->addPending(2);

        $record = app(WorkflowService::class)->startWorkflow($leave);
        $this->assertNotNull($record);
        $this->assertSame($manager->id, (int) $record->approver_id);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $step2 = ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->first();
        $this->assertNotNull($step2);
        $this->assertSame(2, (int) $step2->step_order);
        $this->assertSame($skip->id, (int) $step2->approver_id);

        Sanctum::actingAs($skip);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
    }

    public function test_unresolved_manager_falls_back_to_hr_manager_not_skip(): void
    {
        Log::spy();

        $hr = $this->userWithRole('hr_manager');
        $employee = $this->userWithRole('employee');
        // Yönetici yok (CEO / kök)
        Employee::factory()->forUser($employee)->create(['manager_id' => null]);
        Employee::factory()->forUser($hr)->create();

        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Tek adım manager',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER,
            'is_required' => true,
        ]);

        // İkinci adım — fallback atlamamalı, 1. adımda HR'a düşmeli
        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'Üst',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_SKIP_MANAGER,
            'is_required' => true,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(50)->toDateString(),
            'end_date' => now()->addDays(51)->toDateString(),
            'total_days' => 2,
            'reason' => 'no manager',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $record = app(WorkflowService::class)->startWorkflow($leave);
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->step_order);
        $this->assertSame($hr->id, (int) $record->approver_id);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => $msg === 'approval.approver.unresolved_hr_fallback')
            ->atLeast()
            ->once();
    }

    public function test_delegation_allows_delegate_to_approve_via_motor(): void
    {
        $manager = $this->userWithRole('manager');
        $delegate = $this->userWithRole('employee');
        $employee = $this->userWithRole('employee');

        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($delegate)->create();
        Employee::factory()->forUser($employee)->create(['manager_id' => $managerEmp->id]);

        ApprovalDelegation::create([
            'company_id' => $this->company->id,
            'delegator_id' => $manager->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'entity_type' => null,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Delegation flow',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER,
            'is_required' => true,
        ]);

        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 20,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(60)->toDateString(),
            'end_date' => now()->addDays(61)->toDateString(),
            'total_days' => 2,
            'reason' => 'delegation',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveBalance::where('user_id', $employee->id)->first()?->addPending(2);

        $record = app(WorkflowService::class)->startWorkflow($leave);
        $this->assertNotNull($record);
        // findApprover vekili atar
        $this->assertSame($delegate->id, (int) $record->approver_id);

        Sanctum::actingAs($delegate);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
    }
}
