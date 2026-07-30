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
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-2: Portal izin oluşturma ApprovalFlowEngine'e bağlanır;
 * koşullu adım (total_days > 10) yalnız uzun izinde açılır.
 */
class PortalLeaveApprovalWorkflowQa2Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private LeaveType $leaveType;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'leaves.requests.view',
            'leaves.requests.create',
            'leaves.requests.approve',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        Role::findOrCreate('manager', 'sanctum');
        Role::findOrCreate('employee', 'sanctum');

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
            'name' => 'Yıllık QA2',
            'code' => 'YI-QA2',
            'is_active' => true,
            'default_days' => 30,
        ]);

        $this->manager = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');
        $this->manager->givePermissionTo(['leaves.requests.view', 'leaves.requests.approve']);

        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->employee->assignRole('employee');

        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'manager_id' => null,
        ]);

        // Manager'ı personelin yöneticisi yap — dynamic_manager adımı için
        $emp = Employee::where('user_id', $this->employee->id)->first();
        $mgrEmp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
        ]);
        $emp?->update(['manager_id' => $mgrEmp->id]);

        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 30,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'QA2 Portal Koşullu',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'created_by' => $this->manager->id,
        ]);

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Direkt Yönetici',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->manager->id,
            'is_required' => true,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'Uzun izin (>10)',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->manager->id,
            'is_required' => true,
            'condition' => ['field' => 'total_days', 'op' => '>', 'value' => 10],
        ]);
    }

    public function test_portal_leave_store_creates_approval_instance(): void
    {
        Sanctum::actingAs($this->employee);

        $start = now()->addDays(10)->toDateString();
        $end = now()->addDays(12)->toDateString();

        $res = $this->postJson('/api/v1/portal/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'QA2 3gun',
        ])->assertCreated();

        $leaveId = (int) $res->json('data.id');
        $this->assertGreaterThan(0, $leaveId);

        $this->assertTrue(
            ApprovalInstance::query()
                ->where('approvable_type', LeaveRequest::class)
                ->where('approvable_id', $leaveId)
                ->whereIn('status', [
                    ApprovalInstance::STATUS_PENDING,
                    ApprovalInstance::STATUS_IN_PROGRESS,
                ])
                ->exists()
        );

        $leave = LeaveRequest::findOrFail($leaveId);
        $this->assertNotNull($leave->approval_workflow_id);
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status->value);
    }

    public function test_portal_short_leave_skips_conditional_step_and_approves(): void
    {
        Sanctum::actingAs($this->employee);

        $leaveId = (int) $this->postJson('/api/v1/portal/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'reason' => 'QA2 short',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/leaves/requests/{$leaveId}/approve")->assertOk();

        $leave = LeaveRequest::findOrFail($leaveId);
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);

        $this->assertTrue(
            ApprovalRecord::query()
                ->where('approvable_id', $leaveId)
                ->where('step_order', 2)
                ->where('status', ApprovalRecord::STATUS_SKIPPED)
                ->exists()
        );
    }

    public function test_portal_long_leave_requires_conditional_step(): void
    {
        Sanctum::actingAs($this->employee);

        $leaveId = (int) $this->postJson('/api/v1/portal/leaves', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(44)->toDateString(),
            'reason' => 'QA2 long 15',
        ])->assertCreated()->json('data.id');

        $leave = LeaveRequest::findOrFail($leaveId);
        $this->assertSame(15.0, (float) $leave->total_days);

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/leaves/requests/{$leaveId}/approve")->assertOk();

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status->value);

        $step2 = ApprovalRecord::query()
            ->where('approvable_id', $leaveId)
            ->where('is_current', true)
            ->first();
        $this->assertNotNull($step2);
        $this->assertSame(2, (int) $step2->step_order);

        $this->postJson("/api/v1/leaves/requests/{$leaveId}/approve")->assertOk();
        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status->value);
    }

    public function test_portal_leaves_index_data_is_flat_array_not_nested(): void
    {
        Sanctum::actingAs($this->employee);

        LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'total_days' => 1,
            'reason' => 'shape',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $res = $this->getJson('/api/v1/portal/leaves')->assertOk();
        $data = $res->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertIsInt($data[0]['id'] ?? null);
        // FE hatası: data.data.data — sözleşme: data düz dizi, iç içe data yok
        $this->assertArrayNotHasKey('data', $data);
        $this->assertArrayHasKey('meta', $res->json());
    }
}
