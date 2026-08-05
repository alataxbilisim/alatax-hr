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
use App\Models\EmployeeRequest;
use App\Models\Module;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * W1 — EmployeeRequest motor bağlama, çift onay yok, geçiş verisi, koşullu adım, iptal.
 */
class EmployeeRequestApprovalWorkflowW1Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $employee;

    private User $approver;

    private User $ik;

    private RequestType $requestType;

    private Employee $employeeRow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $module = Module::firstOrCreate(
            ['slug' => 'leave-management'],
            ['name' => 'leave-management', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->approver = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);

        $this->ik = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);

        $this->employee = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->employee->assignRole('employee');

        $mgr = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->approver->id,
        ]);
        $this->employeeRow = Employee::factory()->forUser($this->employee)->create([
            'company_id' => $this->company->id,
            'manager_id' => $mgr->id,
        ]);

        $this->requestType = RequestType::create([
            'company_id' => $this->company->id,
            'name' => 'Avans',
            'slug' => 'avans-w1',
            'is_active' => true,
            'requires_approval' => true,
            'requires_attachment' => false,
            'sort_order' => 1,
        ]);

        $wf = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'W1 Talep Koşullu',
            'entity_type' => ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'created_by' => $this->approver->id,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $wf->id,
            'step_order' => 1,
            'name' => 'Yönetici',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approver->id,
            'is_required' => true,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $wf->id,
            'step_order' => 2,
            'name' => 'İK (high/urgent)',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->ik->id,
            'is_required' => true,
            'condition' => ['field' => 'priority', 'op' => 'in', 'value' => ['high', 'urgent']],
        ]);
    }

    public function test_portal_create_starts_instance(): void
    {
        Sanctum::actingAs($this->employee);
        $id = (int) $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $this->requestType->id,
            'title' => 'W1 create',
            'priority' => 'normal',
        ])->assertCreated()->json('data.id');

        $this->assertTrue(
            ApprovalInstance::query()
                ->where('approvable_type', EmployeeRequest::class)
                ->where('approvable_id', $id)
                ->where('status', ApprovalInstance::STATUS_IN_PROGRESS)
                ->exists()
        );
    }

    public function test_dual_approve_blocked_when_instance_open(): void
    {
        Sanctum::actingAs($this->employee);
        $id = (int) $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $this->requestType->id,
            'title' => 'W1 dual',
            'priority' => 'normal',
        ])->assertCreated()->json('data.id');

        $row = EmployeeRequest::findOrFail($id);

        $this->expectException(RuntimeException::class);
        $row->approve('legacy deneme');
    }

    public function test_motor_approve_completes_request(): void
    {
        Sanctum::actingAs($this->employee);
        $id = (int) $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $this->requestType->id,
            'title' => 'W1 motor',
            'priority' => 'normal',
        ])->assertCreated()->json('data.id');

        $record = ApprovalRecord::query()
            ->where('approvable_type', EmployeeRequest::class)
            ->where('approvable_id', $id)
            ->where('is_current', true)
            ->firstOrFail();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/approvals/{$record->id}/approve")->assertOk();

        $this->assertSame(EmployeeRequest::STATUS_APPROVED, EmployeeRequest::findOrFail($id)->status);
    }

    public function test_legacy_orphan_pending_still_approvable(): void
    {
        // Geçiş verisi (ii): instance yok → model approve çalışır
        $orphan = EmployeeRequest::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employeeRow->id,
            'request_type_id' => $this->requestType->id,
            'title' => 'Orphan pre-W1',
            'priority' => 'normal',
            'status' => EmployeeRequest::STATUS_PENDING,
            'created_by' => $this->employee->id,
        ]);

        Sanctum::actingAs($this->approver);
        $orphan->approve('legacy bridge');

        $this->assertSame(EmployeeRequest::STATUS_APPROVED, $orphan->fresh()->status);
        $this->assertFalse(
            ApprovalInstance::query()
                ->where('approvable_type', EmployeeRequest::class)
                ->where('approvable_id', $orphan->id)
                ->exists()
        );
    }

    public function test_conditional_step_priority_high_requires_ik(): void
    {
        Sanctum::actingAs($this->employee);
        $id = (int) $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $this->requestType->id,
            'title' => 'W1 high',
            'priority' => 'high',
        ])->assertCreated()->json('data.id');

        $r1 = ApprovalRecord::query()
            ->where('approvable_id', $id)
            ->where('is_current', true)
            ->firstOrFail();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/approvals/{$r1->id}/approve")->assertOk();

        $this->assertSame(EmployeeRequest::STATUS_PENDING, EmployeeRequest::findOrFail($id)->status);

        $r2 = ApprovalRecord::query()
            ->where('approvable_id', $id)
            ->where('is_current', true)
            ->firstOrFail();
        $this->assertSame(2, (int) $r2->step_order);

        Sanctum::actingAs($this->ik);
        $this->postJson("/api/v1/approvals/{$r2->id}/approve")->assertOk();
        $this->assertSame(EmployeeRequest::STATUS_APPROVED, EmployeeRequest::findOrFail($id)->status);
    }

    public function test_conditional_step_skipped_for_normal_priority(): void
    {
        Sanctum::actingAs($this->employee);
        $id = (int) $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $this->requestType->id,
            'title' => 'W1 normal',
            'priority' => 'normal',
        ])->assertCreated()->json('data.id');

        $r1 = ApprovalRecord::query()
            ->where('approvable_id', $id)
            ->where('is_current', true)
            ->firstOrFail();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/approvals/{$r1->id}/approve")->assertOk();

        $this->assertSame(EmployeeRequest::STATUS_APPROVED, EmployeeRequest::findOrFail($id)->status);
        $this->assertTrue(
            ApprovalRecord::query()
                ->where('approvable_id', $id)
                ->where('step_order', 2)
                ->where('status', ApprovalRecord::STATUS_SKIPPED)
                ->exists()
        );
    }

    public function test_cancel_closes_open_instance(): void
    {
        Sanctum::actingAs($this->employee);
        $id = (int) $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $this->requestType->id,
            'title' => 'W1 cancel',
            'priority' => 'normal',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/portal/requests/{$id}/cancel")->assertOk();

        $this->assertSame(EmployeeRequest::STATUS_CANCELLED, EmployeeRequest::findOrFail($id)->status);
        $this->assertTrue(
            ApprovalInstance::query()
                ->where('approvable_id', $id)
                ->where('approvable_type', EmployeeRequest::class)
                ->where('status', ApprovalInstance::STATUS_CANCELLED)
                ->exists()
        );
    }

    public function test_workflow_missing_stays_pending_no_auto_approve(): void
    {
        ApprovalWorkflow::query()
            ->where('company_id', $this->company->id)
            ->where('entity_type', ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST)
            ->update(['is_active' => false]);

        Sanctum::actingAs($this->employee);
        $id = (int) $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $this->requestType->id,
            'title' => 'W1 no wf',
            'priority' => 'normal',
        ])->assertCreated()->json('data.id');

        $this->assertSame(EmployeeRequest::STATUS_PENDING, EmployeeRequest::findOrFail($id)->status);
        $this->assertFalse(
            ApprovalInstance::query()
                ->where('approvable_id', $id)
                ->where('approvable_type', EmployeeRequest::class)
                ->exists()
        );

        // Geçiş / legacy: hâlâ sonuçlandırılabilir
        Sanctum::actingAs($this->approver);
        EmployeeRequest::findOrFail($id)->approve('no-wf legacy');
        $this->assertSame(EmployeeRequest::STATUS_APPROVED, EmployeeRequest::findOrFail($id)->status);
    }
}
