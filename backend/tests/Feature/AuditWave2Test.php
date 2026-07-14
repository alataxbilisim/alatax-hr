<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Faz 2 Audit v2 Dalga 2 — Auditable yayılımı + Spatie pivot özel loglar.
 */
class AuditWave2Test extends TestCase
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
            'management.roles.view',
            'management.roles.create',
            'management.roles.edit',
            'management.roles.delete',
            'management.users.view',
            'management.users.edit',
            'management.*',
            'leaves.requests.view',
            'leaves.requests.create',
            'leaves.requests.approve',
            'leaves.*',
            'expenses.claims.view',
            'expenses.claims.approve',
            'expenses.*',
            'employees.departments.view',
            'employees.departments.edit',
            'employees.*',
            'documents.list.view',
            'documents.list.edit',
            'documents.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        Permission::findOrCreate('dummy.perm.a', 'sanctum');
        Permission::findOrCreate('dummy.perm.b', 'sanctum');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'leave-management');
        $this->enableModule($this->company, 'expense-management');

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN',
            'is_active' => true,
            'default_days' => 14,
        ]);
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

    private function companyAdmin(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $user->givePermissionTo([
            'management.roles.view',
            'management.roles.create',
            'management.roles.edit',
            'management.users.view',
            'management.users.edit',
        ]);

        return $user;
    }

    private function hrManager(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $user->assignRole('hr_manager');
        $user->givePermissionTo([
            'leaves.requests.view',
            'leaves.requests.approve',
            'leaves.*',
            'expenses.claims.view',
            'expenses.claims.approve',
            'expenses.*',
            'employees.*',
            'documents.*',
        ]);

        return $user;
    }

    public function test_user_update_creates_audit_diff(): void
    {
        $admin = $this->companyAdmin();
        $target = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Eski Ad',
            'type' => UserType::User,
        ]);

        Sanctum::actingAs($admin);
        $before = ActivityLog::where('model_type', User::class)
            ->where('model_id', $target->id)
            ->where('action', 'update')
            ->count();

        $target->update(['name' => 'Yeni Ad']);

        $logs = ActivityLog::where('model_type', User::class)
            ->where('model_id', $target->id)
            ->where('action', 'update')
            ->get();

        $this->assertSame($before + 1, $logs->count());
        $this->assertSame('Yeni Ad', $logs->last()->new_values['name'] ?? null);
        $this->assertSame('Eski Ad', $logs->last()->old_values['name'] ?? null);
    }

    public function test_user_two_factor_secret_is_masked_in_audit(): void
    {
        $admin = $this->companyAdmin();
        $target = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);

        Sanctum::actingAs($admin);

        $target->update(['two_factor_secret' => 'plain-secret-value']);

        $log = ActivityLog::where('model_type', User::class)
            ->where('model_id', $target->id)
            ->where('action', 'update')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('*** güncellendi', $log->new_values['two_factor_secret'] ?? null);
        $this->assertStringNotContainsString('plain-secret-value', json_encode($log->new_values));
    }

    public function test_department_update_creates_audit_and_no_duplicate_manual(): void
    {
        Sanctum::actingAs($this->companyAdmin());

        $dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'IT',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $createCount = ActivityLog::where('model_type', Department::class)
            ->where('model_id', $dept->id)
            ->where('action', 'create')
            ->count();
        $this->assertSame(1, $createCount);

        $dept->update(['name' => 'IT Ops']);

        $updates = ActivityLog::where('model_type', Department::class)
            ->where('model_id', $dept->id)
            ->where('action', 'update')
            ->get();

        $this->assertCount(1, $updates);
        $this->assertSame('IT Ops', $updates->first()->new_values['name'] ?? null);
    }

    public function test_company_settings_update_masks_secrets_and_keeps_structure(): void
    {
        Sanctum::actingAs($this->companyAdmin());

        $this->company->update([
            'settings' => [
                'smtp' => [
                    'host' => 'smtp.example.com',
                    'password' => 'super-secret-smtp',
                ],
            ],
        ]);

        $log = ActivityLog::where('model_type', Company::class)
            ->where('model_id', $this->company->id)
            ->where('action', 'update')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('smtp.example.com', $log->new_values['settings']['smtp']['host'] ?? null);
        $this->assertSame('*** güncellendi', $log->new_values['settings']['smtp']['password'] ?? null);
        $this->assertStringNotContainsString('super-secret-smtp', json_encode($log->new_values));
    }

    public function test_leave_request_update_and_approve_special_event(): void
    {
        $hr = $this->hrManager();
        $owner = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'total_days' => 3,
            'reason' => 'Tatil',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($hr);

        $leave->update(['reason' => 'Aile ziyareti']);

        $updateLogs = ActivityLog::where('model_type', LeaveRequest::class)
            ->where('model_id', $leave->id)
            ->where('action', 'update')
            ->get();
        $this->assertCount(1, $updateLogs);
        $this->assertSame('Aile ziyareti', $updateLogs->first()->new_values['reason'] ?? null);

        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")
            ->assertStatus(200);

        $approved = ActivityLog::where('model_type', LeaveRequest::class)
            ->where('model_id', $leave->id)
            ->where('action', 'approved')
            ->count();
        $this->assertSame(1, $approved);

        // Özel olay varken observer update üretmemeli (withoutAuditing)
        $statusUpdates = ActivityLog::where('model_type', LeaveRequest::class)
            ->where('model_id', $leave->id)
            ->where('action', 'update')
            ->where('new_values->status', LeaveRequest::STATUS_APPROVED)
            ->count();
        $this->assertSame(0, $statusUpdates);
    }

    public function test_expense_claim_approve_special_event_still_logged(): void
    {
        $hr = $this->hrManager();
        $owner = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);

        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'status' => ExpenseClaim::STATUS_SUBMITTED,
        ]);

        Sanctum::actingAs($hr);

        $this->postJson("/api/v1/expenses/claims/{$claim->id}/approve")
            ->assertStatus(200);

        $this->assertSame(1, ActivityLog::where('model_type', ExpenseClaim::class)
            ->where('model_id', $claim->id)
            ->where('action', 'approved')
            ->count());

        $this->assertSame(0, ActivityLog::where('model_type', ExpenseClaim::class)
            ->where('model_id', $claim->id)
            ->where('action', 'update')
            ->count());
    }

    public function test_role_permission_sync_is_logged(): void
    {
        $admin = $this->companyAdmin();
        Sanctum::actingAs($admin);

        $role = Role::create([
            'name' => 'custom_audit_role',
            'guard_name' => 'sanctum',
        ]);
        $role->syncPermissions(['dummy.perm.a']);

        ActivityLog::query()->delete();

        $this->putJson("/api/v1/roles/{$role->id}", [
            'permissions' => ['dummy.perm.a', 'dummy.perm.b'],
        ])->assertStatus(200);

        $sync = ActivityLog::where('model_type', Role::class)
            ->where('model_id', $role->id)
            ->where('action', 'permission_sync')
            ->first();

        $this->assertNotNull($sync);
        $this->assertContains('dummy.perm.b', $sync->new_values['permissions'] ?? []);
        $this->assertSame($this->company->id, $sync->company_id);
    }

    public function test_user_role_sync_is_logged_on_update(): void
    {
        $admin = $this->companyAdmin();
        $target = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('manager', 'sanctum');

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [$role->id],
        ])->assertStatus(200);

        $this->assertSame(1, ActivityLog::where('model_type', User::class)
            ->where('model_id', $target->id)
            ->where('action', 'role_sync')
            ->count());
    }

    public function test_document_and_employee_document_auditable(): void
    {
        Sanctum::actingAs($this->hrManager());

        $doc = Document::create([
            'company_id' => $this->company->id,
            'name' => 'Sözleşme',
            'file_name' => 'soz.pdf',
            'file_path' => 'documents/1/soz.pdf',
            'file_size' => 100,
            'file_type' => 'application/pdf',
            'version' => 1,
            'uploaded_by' => auth()->id(),
        ]);

        $doc->update(['name' => 'Sözleşme v2']);

        $this->assertSame(1, ActivityLog::where('model_type', Document::class)
            ->where('model_id', $doc->id)
            ->where('action', 'update')
            ->count());

        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $empDoc = EmployeeDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'title' => 'Kimlik',
            'category' => 'id_card',
            'file_path' => 'employees/1/id.pdf',
            'file_name' => 'id.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 50,
            'status' => 'active',
            'uploaded_by' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        $empDoc->update(['title' => 'Kimlik Kopyası']);

        $this->assertSame(1, ActivityLog::where('model_type', EmployeeDocument::class)
            ->where('model_id', $empDoc->id)
            ->where('action', 'update')
            ->count());
    }

    public function test_audit_logs_are_tenant_isolated(): void
    {
        Sanctum::actingAs($this->companyAdmin());

        $dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'HR',
            'code' => 'HR',
            'is_active' => true,
        ]);

        $otherDept = Department::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other HR',
            'code' => 'OHR',
            'is_active' => true,
        ]);

        $otherDept->update(['name' => 'Other HR 2']);

        $ownLogs = ActivityLog::where('company_id', $this->company->id)
            ->where('model_type', Department::class)
            ->where('model_id', $dept->id)
            ->count();
        $this->assertGreaterThan(0, $ownLogs);

        // Yanlış tenant etiketi: diğer firmanın kaydı bu firmaya yazılmamalı
        $leaked = ActivityLog::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('model_type', Department::class)
            ->where('model_id', $otherDept->id)
            ->count();
        $this->assertSame(0, $leaked);

        $otherCompanyLogs = ActivityLog::withoutGlobalScopes()
            ->where('company_id', $this->otherCompany->id)
            ->where('model_type', Department::class)
            ->where('model_id', $otherDept->id)
            ->count();
        $this->assertGreaterThan(0, $otherCompanyLogs);
    }
}
