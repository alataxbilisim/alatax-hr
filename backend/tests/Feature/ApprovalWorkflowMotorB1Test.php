<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
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
use App\Services\WorkflowService;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 4B B1: sıralı çok adım + red + yeniden gönderim.
 */
class ApprovalWorkflowMotorB1Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private LeaveType $leaveType;

    private User $approver1;

    private User $approver2;

    private User $requester;

    private ApprovalWorkflow $workflow;

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
            'name' => 'Annual B1',
            'code' => 'AN-B1',
            'is_active' => true,
            'default_days' => 20,
        ]);

        $this->approver1 = $this->userWithPerms('manager');
        $this->approver2 = $this->userWithPerms('manager');
        $this->requester = $this->userWithPerms('employee');

        Employee::factory()->forUser($this->approver1)->create();
        Employee::factory()->forUser($this->approver2)->create();
        Employee::factory()->forUser($this->requester)->create();

        // 2 adımlı akış (default değil — bilinçli test flow)
        $this->workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'İki Adımlı İzin',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $this->workflow->id,
            'step_order' => 1,
            'name' => 'Yönetici',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approver1->id,
            'is_required' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $this->workflow->id,
            'step_order' => 2,
            'name' => 'İK',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approver2->id,
            'is_required' => true,
        ]);
    }

    private function userWithPerms(string $role): User
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

    private function startLeave(): LeaveRequest
    {
        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->requester->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 20,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $this->requester->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
            'total_days' => 2,
            'reason' => 'B1 multi-step',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        LeaveBalance::where('user_id', $this->requester->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->first()
            ?->addPending(2);

        $record = app(WorkflowService::class)->startWorkflow($leave);
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->step_order);
        $this->assertSame($this->approver1->id, (int) $record->approver_id);

        return $leave->fresh();
    }

    public function test_step2_cannot_approve_before_step1(): void
    {
        $leave = $this->startLeave();

        Sanctum::actingAs($this->approver2);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")
            ->assertStatus(403);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status->value);
        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->value('step_order'));
    }

    public function test_two_step_flow_completes_and_writes_records(): void
    {
        $leave = $this->startLeave();

        Sanctum::actingAs($this->approver1);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")
            ->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status->value);
        $this->assertSame(2, (int) $leave->current_step);

        $current = ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->first();
        $this->assertNotNull($current);
        $this->assertSame(2, (int) $current->step_order);
        $this->assertSame($this->approver2->id, (int) $current->approver_id);

        Sanctum::actingAs($this->approver2);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")
            ->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
        $this->assertSame(LeaveRequest::WORKFLOW_COMPLETED, $leave->workflow_status);

        $this->assertSame(2, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalRecord::STATUS_APPROVED)
            ->count());

        $this->assertDatabaseHas('approval_instances', [
            'approvable_id' => $leave->id,
            'status' => ApprovalInstance::STATUS_APPROVED,
        ]);
    }

    public function test_reject_then_resubmit_creates_new_instance(): void
    {
        $leave = $this->startLeave();
        $firstInstanceId = ApprovalInstance::query()
            ->where('approvable_id', $leave->id)
            ->value('id');

        Sanctum::actingAs($this->approver1);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/reject", [
            'reason' => 'Eksik belge',
        ])->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_REJECTED, $leave->status->value);
        $this->assertSame('Eksik belge', $leave->rejection_reason);

        $this->assertDatabaseHas('approval_instances', [
            'id' => $firstInstanceId,
            'status' => ApprovalInstance::STATUS_REJECTED,
        ]);

        Sanctum::actingAs($this->requester);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/resubmit")
            ->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status->value);

        $instances = ApprovalInstance::query()
            ->where('approvable_id', $leave->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $instances);
        $this->assertSame(ApprovalInstance::STATUS_REJECTED, $instances[0]->status);
        $this->assertSame(ApprovalInstance::STATUS_IN_PROGRESS, $instances[1]->status);

        // Eski reddedilmiş kayıt + yeni pending adım1
        $this->assertTrue(
            ApprovalRecord::query()
                ->where('approvable_id', $leave->id)
                ->where('status', ApprovalRecord::STATUS_REJECTED)
                ->exists()
        );
        $this->assertDatabaseHas('approval_records', [
            'approvable_id' => $leave->id,
            'approval_instance_id' => $instances[1]->id,
            'step_order' => 1,
            'status' => ApprovalRecord::STATUS_PENDING,
            'is_current' => true,
        ]);
    }
}
