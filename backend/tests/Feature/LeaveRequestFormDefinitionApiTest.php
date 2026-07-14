<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Events\ApprovalRequested;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use App\Services\DefaultLeaveApprovalWorkflowService;
use App\Services\FormFieldCatalogService;
use Database\Seeders\EmployeeFormFieldSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4A-3 Zincir 1 — leave_request Form Engine katalog + API + store regresyon.
 */
class LeaveRequestFormDefinitionApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        $this->seed(EmployeeFormFieldSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->companyB = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->companyA, 'leave-management');
        $this->enableModule($this->companyB, 'leave-management');

        $this->adminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminA);
        $this->adminA = $this->adminA->fresh();

        $this->adminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminB);
        $this->adminB = $this->adminB->fresh();

        $this->leaveType = LeaveType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Yıllık İzin',
            'code' => 'ANNUAL-FE',
            'is_active' => true,
            'default_days' => 14,
            'requires_document' => false,
        ]);

        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($this->companyA);
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

    public function test_database_is_pgsql_testing(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('alatax_hr_testing', DB::connection()->getDatabaseName());
    }

    public function test_leave_request_catalog_seed_is_idempotent(): void
    {
        $before = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->where('entity_type', 'leave_request')
            ->count();

        $this->assertGreaterThanOrEqual(5, $before);

        app(FormFieldCatalogService::class)->seedSystemCatalog();
        app(FormFieldCatalogService::class)->seedSystemCatalog();

        $after = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->where('entity_type', 'leave_request')
            ->count();

        $this->assertSame($before, $after);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/form-definitions/leave_request')->assertStatus(401);
        $this->putJson('/api/v1/form-definitions/leave_request', [])->assertStatus(401);
    }

    public function test_forbidden_without_forms_permission(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        $role = \App\Models\Role::findOrCreate('viewer_no_forms_leave', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->syncPermissions(['leaves.requests.view']);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/form-definitions/leave_request')->assertStatus(403);
    }

    public function test_get_put_rename_hide_and_tenant_isolation(): void
    {
        Sanctum::actingAs($this->adminA);

        $get = $this->getJson('/api/v1/form-definitions/leave_request');
        $get->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.entity_type', 'leave_request');

        $keys = collect($get->json('data.fields'))->pluck('field_key')->all();
        $this->assertContains('leave_type_id', $keys);
        $this->assertContains('start_date', $keys);
        $this->assertContains('end_date', $keys);
        $this->assertContains('reason', $keys);
        $this->assertContains('document', $keys);

        $put = $this->putJson('/api/v1/form-definitions/leave_request', [
            'name' => 'Firma İzin Formu',
            'fields' => [
                [
                    'system_key' => 'reason',
                    'label_override' => 'İzin Gerekçesi',
                    'is_hidden' => false,
                ],
                [
                    'system_key' => 'document',
                    'is_hidden' => true,
                ],
            ],
        ]);
        $put->assertStatus(200);

        $after = $this->getJson('/api/v1/form-definitions/leave_request');
        $after->assertStatus(200)->assertJsonPath('data.name', 'Firma İzin Formu');
        $reason = collect($after->json('data.fields'))->firstWhere('system_key', 'reason');
        $document = collect($after->json('data.fields'))->firstWhere('system_key', 'document');
        $this->assertSame('İzin Gerekçesi', $reason['effective_label']);
        $this->assertTrue($document['is_hidden']);

        Sanctum::actingAs($this->adminB);
        $getB = $this->getJson('/api/v1/form-definitions/leave_request');
        $getB->assertStatus(200);
        $reasonB = collect($getB->json('data.fields'))->firstWhere('system_key', 'reason');
        $this->assertSame('Açıklama', $reasonB['effective_label']);
    }

    public function test_form_engine_leave_store_preserves_workflow_and_balance(): void
    {
        Event::fake([ApprovalRequested::class]);

        $manager = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        $manager->assignRole('manager');
        $manager->givePermissionTo([
            'leaves.requests.view',
            'leaves.requests.approve',
            'leaves.requests.create',
        ]);

        $employee = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        $employee->assignRole('employee');
        $employee->givePermissionTo([
            'leaves.requests.view',
            'leaves.requests.create',
        ]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $managerEmp = Employee::factory()->forUser($manager)->create([
            'company_id' => $this->companyA->id,
        ]);
        Employee::factory()->forUser($employee)->create([
            'company_id' => $this->companyA->id,
            'manager_id' => $managerEmp->id,
        ]);

        LeaveBalance::create([
            'company_id' => $this->companyA->id,
            'user_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 14,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        Sanctum::actingAs($employee->fresh());

        $start = now()->next(\Carbon\Carbon::MONDAY)->toDateString();
        $end = now()->next(\Carbon\Carbon::MONDAY)->addDay()->toDateString();

        $response = $this->postJson('/api/v1/leaves/requests', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'FormEngine leave pilot',
        ]);

        $response->assertStatus(201);
        $leaveId = (int) $response->json('data.id');

        $this->assertDatabaseHas('approval_instances', [
            'company_id' => $this->companyA->id,
            'approvable_id' => $leaveId,
            'status' => ApprovalInstance::STATUS_IN_PROGRESS,
        ]);

        $this->assertDatabaseHas('approval_records', [
            'approvable_id' => $leaveId,
            'approver_id' => $manager->id,
            'status' => ApprovalRecord::STATUS_PENDING,
            'is_current' => true,
        ]);

        $balance = LeaveBalance::where('user_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->first();
        $this->assertNotNull($balance);
        $this->assertGreaterThan(0, (float) $balance->pending_days);

        Event::assertDispatched(ApprovalRequested::class);
    }
}
