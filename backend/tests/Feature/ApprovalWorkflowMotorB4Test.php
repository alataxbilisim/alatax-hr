<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalEscalationAlert;
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
use App\Notifications\CatalogNotification;
use App\Services\Approval\ApprovalEscalationService;
use App\Services\WorkflowService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 4B B4: paralel grup runtime + eskalasyon (yetki genişlemez).
 */
class ApprovalWorkflowMotorB4Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private LeaveType $leaveType;

    private User $approverA;

    private User $approverB;

    private User $requester;

    private User $managerOfA;

    private User $admin;

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
            'name' => 'Annual B4',
            'code' => 'AN-B4',
            'is_active' => true,
            'default_days' => 20,
        ]);

        $this->managerOfA = $this->userWithPerms('manager');
        $this->approverA = $this->userWithPerms('manager');
        $this->approverB = $this->userWithPerms('manager');
        $this->requester = $this->userWithPerms('employee');
        $this->admin = $this->userWithPerms('admin');
        $this->admin->update(['type' => UserType::CompanyAdmin]);

        $empMgr = Employee::factory()->forUser($this->managerOfA)->create();
        Employee::factory()->forUser($this->approverA)->create(['manager_id' => $empMgr->id]);
        Employee::factory()->forUser($this->approverB)->create();
        Employee::factory()->forUser($this->requester)->create();
        Employee::factory()->forUser($this->admin)->create();
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

    /**
     * @return array{0: ApprovalWorkflow, 1: LeaveRequest}
     */
    private function startParallelLeave(string $policy): array
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => "Paralel {$policy}",
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'escalation_days' => null,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Paralel A',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approverA->id,
            'is_required' => true,
            'parallel_group' => 1,
            'completion_policy' => $policy,
            'escalation_days' => 3,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'Paralel B',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approverB->id,
            'is_required' => true,
            'parallel_group' => 1,
            'completion_policy' => $policy,
            'escalation_days' => 3,
        ]);

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
            'start_date' => now()->addDays(40)->toDateString(),
            'end_date' => now()->addDays(41)->toDateString(),
            'total_days' => 2,
            'reason' => 'B4 parallel',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        LeaveBalance::where('user_id', $this->requester->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->first()
            ?->addPending(2);

        $record = app(WorkflowService::class)->startWorkflow($leave, ['total_days' => 2]);
        $this->assertNotNull($record);

        $pending = ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->where('is_current', true)
            ->count();
        $this->assertSame(2, $pending);

        return [$workflow, $leave->fresh()];
    }

    public function test_parallel_all_requires_both_approvals(): void
    {
        [, $leave] = $this->startParallelLeave(ApprovalStep::COMPLETION_ALL);

        Sanctum::actingAs($this->approverA);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status->value);
        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->where('is_current', true)
            ->count());

        Sanctum::actingAs($this->approverB);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
        $this->assertSame(0, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->count());
    }

    public function test_parallel_any_auto_skips_siblings(): void
    {
        [, $leave] = $this->startParallelLeave(ApprovalStep::COMPLETION_ANY);

        Sanctum::actingAs($this->approverA);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);

        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalRecord::STATUS_APPROVED)
            ->count());
        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalRecord::STATUS_SKIPPED)
            ->where('comment', 'like', '%otomatik atlandı%')
            ->count());
    }

    public function test_parallel_any_race_single_advance(): void
    {
        [, $leave] = $this->startParallelLeave(ApprovalStep::COMPLETION_ANY);

        $recA = ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('approver_id', $this->approverA->id)
            ->where('is_current', true)
            ->firstOrFail();
        $recB = ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('approver_id', $this->approverB->id)
            ->where('is_current', true)
            ->firstOrFail();

        $wf = app(WorkflowService::class);
        $okA = $wf->processAuthorizedApproval($recA, $this->approverA->id, 'a');
        $okB = $wf->processAuthorizedApproval($recB->fresh(), $this->approverB->id, 'b');

        $this->assertTrue($okA);
        $this->assertFalse($okB);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
        $this->assertSame(1, ApprovalInstance::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalInstance::STATUS_APPROVED)
            ->count());
    }

    public function test_parallel_reject_closes_siblings(): void
    {
        [, $leave] = $this->startParallelLeave(ApprovalStep::COMPLETION_ALL);

        Sanctum::actingAs($this->approverB);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/reject", [
            'reason' => 'uygun değil',
        ])->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_REJECTED, $leave->status->value);
        $this->assertSame(0, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->count());
        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalRecord::STATUS_REJECTED)
            ->count());
        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('status', ApprovalRecord::STATUS_SKIPPED)
            ->where('comment', 'like', '%reddi%')
            ->count());
    }

    public function test_null_parallel_group_stays_sequential(): void
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Sıralı NULL',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'A',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approverA->id,
            'is_required' => true,
            'parallel_group' => null,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'B',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approverB->id,
            'is_required' => true,
            'parallel_group' => null,
        ]);

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
            'start_date' => now()->addDays(50)->toDateString(),
            'end_date' => now()->addDays(51)->toDateString(),
            'total_days' => 2,
            'reason' => 'B4 sequential',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveBalance::where('user_id', $this->requester->id)->first()?->addPending(2);

        app(WorkflowService::class)->startWorkflow($leave);

        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->count());

        Sanctum::actingAs($this->approverB);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(403);

        Sanctum::actingAs($this->approverA);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->where('approver_id', $this->approverB->id)
            ->count());
    }

    public function test_escalation_reminder_then_escalate_idempotent_and_tenant_isolated(): void
    {
        Notification::fake();
        Mail::fake();

        [, $leave] = $this->startParallelLeave(ApprovalStep::COMPLETION_ALL);

        $recA = ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('approver_id', $this->approverA->id)
            ->firstOrFail();

        // created_at 3 gün önce → hatırlatma eşiği
        DB::table('approval_records')->where('id', $recA->id)->update([
            'created_at' => Carbon::parse('2026-07-07 10:00:00'),
            'updated_at' => Carbon::parse('2026-07-07 10:00:00'),
        ]);

        $otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
        $foreignApprover = User::factory()->create([
            'company_id' => $otherCompany->id,
            'type' => UserType::User,
        ]);
        $foreignApprover->assignRole('manager');
        Employee::factory()->forUser($foreignApprover)->create();

        $foreignWf = ApprovalWorkflow::create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'escalation_days' => 3,
        ]);
        $foreignStep = ApprovalStep::create([
            'approval_workflow_id' => $foreignWf->id,
            'step_order' => 1,
            'name' => 'F',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $foreignApprover->id,
            'escalation_days' => 3,
        ]);
        $foreignLeave = LeaveRequest::create([
            'company_id' => $otherCompany->id,
            'user_id' => $foreignApprover->id,
            'leave_type_id' => LeaveType::create([
                'company_id' => $otherCompany->id,
                'name' => 'X',
                'code' => 'X',
                'is_active' => true,
                'default_days' => 5,
            ])->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'total_days' => 2,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $foreignInstance = ApprovalInstance::create([
            'company_id' => $otherCompany->id,
            'approval_workflow_id' => $foreignWf->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $foreignLeave->id,
            'current_step' => 1,
            'status' => ApprovalInstance::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $foreignRec = ApprovalRecord::create([
            'company_id' => $otherCompany->id,
            'approval_instance_id' => $foreignInstance->id,
            'approval_workflow_id' => $foreignWf->id,
            'approval_step_id' => $foreignStep->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $foreignLeave->id,
            'approver_id' => $foreignApprover->id,
            'status' => ApprovalRecord::STATUS_PENDING,
            'step_order' => 1,
            'is_current' => true,
        ]);
        DB::table('approval_records')->where('id', $foreignRec->id)->update([
            'created_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        $service = app(ApprovalEscalationService::class);
        $today = Carbon::parse('2026-07-10');

        $r1 = $service->processCompany($this->company->id, $today);
        $this->assertSame(1, $r1['reminded']);

        Notification::assertSentTo($this->approverA, CatalogNotification::class);

        $r2 = $service->processCompany($this->company->id, $today);
        $this->assertSame(0, $r2['reminded']);
        $this->assertSame(1, ApprovalEscalationAlert::query()
            ->where('approval_record_id', $recA->id)
            ->where('alert_level', ApprovalEscalationAlert::LEVEL_REMINDER)
            ->count());

        // Eşik+2 → eskalasyon (üst yönetici)
        DB::table('approval_records')->where('id', $recA->id)->update([
            'created_at' => Carbon::parse('2026-07-05 10:00:00'),
        ]);
        $r3 = $service->processCompany($this->company->id, $today);
        $this->assertSame(1, $r3['escalated']);
        Notification::assertSentTo($this->managerOfA, CatalogNotification::class);

        // Yetki değişmedi
        $recA->refresh();
        $this->assertSame($this->approverA->id, (int) $recA->approver_id);
        $this->assertSame(ApprovalRecord::STATUS_PENDING, $recA->status);

        // Tenant: foreign kayıt bu company koşusunda yok
        $this->assertSame(0, ApprovalEscalationAlert::query()
            ->where('approval_record_id', $foreignRec->id)
            ->count());
    }
}
