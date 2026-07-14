<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 2 Dalga 3 (SON Policy): Document + EmployeeDocument + ApprovalRecord.
 */
class PolicyDataScopeWave3Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'documents.list.view',
            'documents.list.edit',
            'documents.list.delete',
            'documents.list.create',
            'documents.*',
            'employees.list.view',
            'employees.documents.view',
            'employees.documents.edit',
            'employees.documents.create',
            'employees.documents.delete',
            'employees.*',
            'leaves.requests.view',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'document-management');
        $this->enableModule($this->otherCompany, 'document-management');
        $this->enableModule($this->company, 'leave-management');
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

    // ——— EmployeeDocument (görünürlük) ———

    public function test_employee_sees_own_visible_document_not_hidden_or_others(): void
    {
        $owner = $this->userWithRole('employee', ['employees.list.view', 'employees.documents.view']);
        $other = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $ownerEmp = Employee::factory()->forUser($owner)->create();
        $otherEmp = Employee::factory()->forUser($other)->create();

        $visible = EmployeeDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $ownerEmp->id,
            'title' => 'Visible',
            'category' => 'contract',
            'file_path' => 'x/a.pdf',
            'file_name' => 'a.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'is_visible_to_employee' => true,
            'status' => 'active',
            'uploaded_by' => $owner->id,
        ]);
        $hidden = EmployeeDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $ownerEmp->id,
            'title' => 'Hidden',
            'category' => 'contract',
            'file_path' => 'x/b.pdf',
            'file_name' => 'b.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'is_visible_to_employee' => false,
            'status' => 'active',
            'uploaded_by' => $owner->id,
        ]);
        $others = EmployeeDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $otherEmp->id,
            'title' => 'Other',
            'category' => 'contract',
            'file_path' => 'x/c.pdf',
            'file_name' => 'c.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'is_visible_to_employee' => true,
            'status' => 'active',
            'uploaded_by' => $other->id,
        ]);

        Sanctum::actingAs($owner);

        // Portal: visible only
        $portal = $this->getJson('/api/v1/portal/documents')->assertStatus(200);
        $portalIds = collect($portal->json('data'))->pluck('id');
        $this->assertTrue($portalIds->contains($visible->id));
        $this->assertFalse($portalIds->contains($hidden->id));
        $this->assertFalse($portalIds->contains($others->id));

        $this->getJson("/api/v1/portal/documents/{$visible->id}")->assertStatus(200);
        $this->getJson("/api/v1/portal/documents/{$hidden->id}")->assertStatus(403);

        // HR: own employee docs via employees/{id}/documents — own scope sees own employee
        $this->getJson("/api/v1/employees/{$ownerEmp->id}/documents/{$visible->id}")->assertStatus(200);
        $this->getJson("/api/v1/employees/{$ownerEmp->id}/documents/{$hidden->id}")->assertStatus(403);
        $this->getJson("/api/v1/employees/{$otherEmp->id}/documents/{$others->id}")->assertStatus(403);
    }

    public function test_hr_manager_sees_hidden_employee_document(): void
    {
        $hr = $this->userWithRole('hr_manager', [
            'employees.list.view',
            'employees.documents.view',
            'employees.documents.edit',
        ]);
        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $emp = Employee::factory()->forUser($employee)->create();

        $hidden = EmployeeDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'title' => 'HR Only',
            'category' => 'id_card',
            'file_path' => 'x/hr.pdf',
            'file_name' => 'hr.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 50,
            'is_visible_to_employee' => false,
            'status' => 'active',
            'uploaded_by' => $hr->id,
        ]);

        Sanctum::actingAs($hr);

        $this->getJson("/api/v1/employees/{$emp->id}/documents/{$hidden->id}")->assertStatus(200);
        $this->putJson("/api/v1/employees/{$emp->id}/documents/{$hidden->id}", [
            'title' => 'Updated by HR',
        ])->assertStatus(200);
    }

    public function test_document_company_scope_lists_and_tenant_isolation(): void
    {
        $hr = $this->userWithRole('hr_manager', ['documents.list.view', 'documents.list.edit']);
        $uploader = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $ownDoc = Document::create([
            'company_id' => $this->company->id,
            'name' => 'Company Doc',
            'file_name' => 'c.pdf',
            'file_path' => 'documents/1/c.pdf',
            'file_size' => 10,
            'file_type' => 'application/pdf',
            'uploaded_by' => $uploader->id,
        ]);
        $otherDoc = Document::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Co',
            'file_name' => 'o.pdf',
            'file_path' => 'documents/2/o.pdf',
            'file_size' => 10,
            'file_type' => 'application/pdf',
            'uploaded_by' => $uploader->id,
        ]);

        Sanctum::actingAs($hr);

        $list = $this->getJson('/api/v1/documents')->assertStatus(200);
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownDoc->id));
        $this->assertFalse($ids->contains($otherDoc->id));

        $this->getJson("/api/v1/documents/{$ownDoc->id}")->assertStatus(200);
        $this->getJson("/api/v1/documents/{$otherDoc->id}")->assertStatus(404);
    }

    // ——— ApprovalRecord ———

    public function test_assigned_approver_can_approve_other_cannot(): void
    {
        $approver = $this->userWithRole('manager');
        $stranger = $this->userWithRole('employee');
        Employee::factory()->forUser($approver)->create();
        Employee::factory()->forUser($stranger)->create();

        $requester = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN',
            'is_active' => true,
            'default_days' => 14,
        ]);
        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'total_days' => 2,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $record = $this->makeApprovalRecord($approver, $leave);

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/approvals/{$record->id}/approve")->assertStatus(200);

        $record2 = $this->makeApprovalRecord($approver, $leave);
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/approvals/{$record2->id}/approve")->assertStatus(403);
    }

    public function test_delegate_can_approve(): void
    {
        $delegator = $this->userWithRole('manager');
        $delegate = $this->userWithRole('employee');
        Employee::factory()->forUser($delegator)->create();
        Employee::factory()->forUser($delegate)->create();

        ApprovalDelegation::create([
            'company_id' => $this->company->id,
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'entity_type' => null,
            'is_active' => true,
            'created_by' => $delegator->id,
        ]);

        $requester = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual2',
            'code' => 'AN2',
            'is_active' => true,
            'default_days' => 14,
        ]);
        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'total_days' => 2,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $record = $this->makeApprovalRecord($delegator, $leave);

        Sanctum::actingAs($delegate);
        $this->postJson("/api/v1/approvals/{$record->id}/approve")->assertStatus(200);
    }

    public function test_company_admin_with_admin_role_approvals(): void
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin);
        $approver = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $requester = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual3',
            'code' => 'AN3',
            'is_active' => true,
            'default_days' => 14,
        ]);
        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(12)->toDateString(),
            'end_date' => now()->addDays(13)->toDateString(),
            'total_days' => 2,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $record = $this->makeApprovalRecord($approver, $leave);

        Sanctum::actingAs($admin->fresh());
        $this->postJson("/api/v1/approvals/{$record->id}/approve")->assertStatus(200);
    }

    private function makeApprovalRecord(User $approver, LeaveRequest $leave): ApprovalRecord
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Leave WF',
            'entity_type' => 'leave_request',
            'is_active' => true,
            'is_default' => true,
        ]);

        $step = ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Manager',
            'approver_type' => 'specific_user',
            'specific_user_id' => $approver->id,
            'is_required' => true,
            'can_skip' => false,
        ]);

        return ApprovalRecord::create([
            'company_id' => $this->company->id,
            'approval_workflow_id' => $workflow->id,
            'approval_step_id' => $step->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $leave->id,
            'approver_id' => $approver->id,
            'status' => ApprovalRecord::STATUS_PENDING,
            'step_order' => 1,
            'is_current' => true,
        ]);
    }
}
