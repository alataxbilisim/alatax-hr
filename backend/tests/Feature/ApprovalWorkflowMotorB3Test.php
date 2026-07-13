<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
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
use App\Services\ApprovalStepConditionEvaluator;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 4B B3: koşullu adımlar (whitelist evaluator).
 */
class ApprovalWorkflowMotorB3Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private LeaveType $leaveType;

    private User $manager;

    private User $gm;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'leaves.requests.view',
            'leaves.requests.create',
            'leaves.requests.approve',
            'leaves.requests.edit',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['manager', 'employee', 'hr_manager'] as $roleName) {
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
            'name' => 'Annual B3',
            'code' => 'AN-B3',
            'is_active' => true,
            'default_days' => 30,
        ]);

        $this->manager = $this->userWithRole('manager');
        $this->gm = $this->userWithRole('manager');
        $this->employee = $this->userWithRole('employee');

        $managerEmp = Employee::factory()->forUser($this->manager)->create();
        Employee::factory()->forUser($this->gm)->create();
        Employee::factory()->forUser($this->employee)->create(['manager_id' => $managerEmp->id]);

        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Koşullu GM eşiği',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Yönetici',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->manager->id,
            'is_required' => true,
            'condition' => null,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'Genel Müdür',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->gm->id,
            'is_required' => true,
            'condition' => [
                'field' => 'total_days',
                'op' => '>',
                'value' => 10,
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
            'leaves.requests.create',
            'leaves.requests.approve',
            'leaves.requests.edit',
        ]);

        return $user;
    }

    private function leaveWithDays(float $days): LeaveRequest
    {
        LeaveBalance::firstOrCreate(
            [
                'user_id' => $this->employee->id,
                'leave_type_id' => $this->leaveType->id,
                'year' => now()->year,
            ],
            [
                'company_id' => $this->company->id,
                'total_days' => 30,
                'used_days' => 0,
                'pending_days' => 0,
            ]
        );

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(70)->toDateString(),
            'end_date' => now()->addDays(70 + (int) ceil($days))->toDateString(),
            'total_days' => $days,
            'reason' => "B3 {$days} days",
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->first()
            ?->addPending($days);

        return $leave;
    }

    public function test_short_leave_skips_gm_step(): void
    {
        $leave = $this->leaveWithDays(3);
        $record = app(WorkflowService::class)->startWorkflow($leave, ['total_days' => 3]);
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->step_order);

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);

        $this->assertTrue(
            ApprovalRecord::query()
                ->where('approvable_id', $leave->id)
                ->where('step_order', 2)
                ->where('status', ApprovalRecord::STATUS_SKIPPED)
                ->exists()
        );

        $this->assertFalse(
            ApprovalRecord::query()
                ->where('approvable_id', $leave->id)
                ->where('step_order', 2)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->exists()
        );
    }

    public function test_long_leave_requires_gm_step(): void
    {
        $leave = $this->leaveWithDays(15);
        $record = app(WorkflowService::class)->startWorkflow($leave, ['total_days' => 15]);
        $this->assertNotNull($record);

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status->value);

        $gmRecord = ApprovalRecord::query()
            ->where('approvable_id', $leave->id)
            ->where('is_current', true)
            ->first();

        $this->assertNotNull($gmRecord);
        $this->assertSame(2, (int) $gmRecord->step_order);
        $this->assertSame($this->gm->id, (int) $gmRecord->approver_id);

        Sanctum::actingAs($this->gm);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
    }

    public function test_evaluator_rejects_unknown_field(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $leave = $this->leaveWithDays(3);
        app(ApprovalStepConditionEvaluator::class)->matches(
            ['field' => 'evil_eval', 'op' => '>', 'value' => 1],
            $leave
        );
    }
}
