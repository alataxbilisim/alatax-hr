<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalInstance;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\RequestType;
use App\Models\SalaryReviewPeriod;
use App\Models\User;
use App\Services\Approval\ApprovalEntityRegistry;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * W1 yapısal guard: registry'deki HER entity için gerçek API → approval_instance.
 * Yeni entity kaydedilip probe eklenmezse test KIRMIZI.
 */
class ApprovalEntityRegistryStructuralTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $employeeUser;

    private User $approver;

    private User $admin;

    private LeaveType $leaveType;

    private ExpenseCategory $expenseCategory;

    private RequestType $requestType;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        foreach (['leave-management', 'expense-management'] as $slug) {
            $module = Module::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'is_core' => false, 'is_active' => true]
            );
            $this->company->modules()->syncWithoutDetaching([
                $module->id => ['is_active' => true, 'activated_at' => now()],
            ]);
        }

        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();

        $this->admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo([
            'employees.salary.view',
            'employees.salary.edit',
            'employees.list.view',
            'management.kvkk.requests.view',
            'management.kvkk.requests.edit',
            'management.kvkk.requests.respond',
        ]);

        $this->approver = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->approver->givePermissionTo([
            'leaves.requests.approve',
            'employees.salary.view',
            'employees.salary.edit',
            'management.kvkk.requests.view',
            'management.kvkk.requests.edit',
            'management.kvkk.requests.respond',
        ]);

        $this->employeeUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->employeeUser->assignRole('employee');

        $mgrEmp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->approver->id,
        ]);
        $this->employee = Employee::factory()->forUser($this->employeeUser)->create([
            'company_id' => $this->company->id,
            'manager_id' => $mgrEmp->id,
            'gross_salary' => 40000,
            'currency' => 'TRY',
            'status' => 'active',
        ]);

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'W1 Yıllık',
            'code' => 'W1-YI',
            'is_active' => true,
            'default_days' => 30,
        ]);
        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employeeUser->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 30,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $this->expenseCategory = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'W1 Kategori',
            'is_active' => true,
        ]);

        $this->requestType = RequestType::create([
            'company_id' => $this->company->id,
            'name' => 'W1 Genel',
            'slug' => 'w1-genel',
            'is_active' => true,
            'requires_approval' => true,
            'requires_attachment' => false,
            'sort_order' => 1,
        ]);

        foreach ([
            ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            ApprovalWorkflow::ENTITY_EXPENSE_REQUEST,
            ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST,
            ApprovalWorkflow::ENTITY_SALARY_REVIEW,
            ApprovalWorkflow::ENTITY_DATA_SUBJECT_REQUEST,
        ] as $entityType) {
            $wf = ApprovalWorkflow::create([
                'company_id' => $this->company->id,
                'name' => 'W1 Structural '.$entityType,
                'entity_type' => $entityType,
                'is_active' => true,
                'is_default' => true,
                'created_by' => $this->admin->id,
            ]);
            ApprovalStep::create([
                'approval_workflow_id' => $wf->id,
                'step_order' => 1,
                'name' => 'Onaycı',
                'approver_type' => ApprovalStep::APPROVER_USER,
                'specific_user_id' => $this->approver->id,
                'is_required' => true,
            ]);
        }
    }

    public function test_registry_keys_match_structural_probes(): void
    {
        $registryKeys = array_keys(app(ApprovalEntityRegistry::class)->all());
        sort($registryKeys);
        $probeKeys = array_keys($this->structuralProbes());
        sort($probeKeys);

        $this->assertSame(
            $registryKeys,
            $probeKeys,
            'ApprovalEntityRegistry ile yapısal probe anahtarları birebir olmalı'
        );
    }

    public function test_every_registered_entity_creates_instance_via_real_api(): void
    {
        $registry = app(ApprovalEntityRegistry::class);

        foreach ($registry->all() as $entityType => $def) {
            $probes = $this->structuralProbes();
            $this->assertArrayHasKey($entityType, $probes, "Probe eksik: {$entityType}");

            $approvableId = ($probes[$entityType])();

            $this->assertGreaterThan(0, $approvableId, "API id yok: {$entityType}");

            $exists = ApprovalInstance::query()
                ->where('approvable_type', $def->modelClass)
                ->where('approvable_id', $approvableId)
                ->whereIn('status', [
                    ApprovalInstance::STATUS_PENDING,
                    ApprovalInstance::STATUS_IN_PROGRESS,
                ])
                ->exists();

            $this->assertTrue(
                $exists,
                "approval_instance oluşmadı: {$entityType} model={$def->modelClass} id={$approvableId}"
            );
        }
    }

    /**
     * @return array<string, callable(): int>
     */
    private function structuralProbes(): array
    {
        return [
            ApprovalWorkflow::ENTITY_LEAVE_REQUEST => function (): int {
                Sanctum::actingAs($this->employeeUser);

                return (int) $this->postJson('/api/v1/portal/leaves', [
                    'leave_type_id' => $this->leaveType->id,
                    'start_date' => now()->addDays(10)->toDateString(),
                    'end_date' => now()->addDays(12)->toDateString(),
                    'reason' => 'W1 structural leave',
                ])->assertCreated()->json('data.id');
            },
            ApprovalWorkflow::ENTITY_EXPENSE_REQUEST => function (): int {
                $claim = ExpenseClaim::factory()->create([
                    'company_id' => $this->company->id,
                    'user_id' => $this->employeeUser->id,
                    'status' => ExpenseClaim::STATUS_DRAFT,
                    'total_amount' => 120,
                ]);
                ExpenseItem::create([
                    'expense_claim_id' => $claim->id,
                    'expense_category_id' => $this->expenseCategory->id,
                    'description' => 'W1 kalem',
                    'item_date' => now()->toDateString(),
                    'amount' => 120,
                ]);

                Sanctum::actingAs($this->employeeUser);
                $this->postJson("/api/v1/portal/expenses/{$claim->id}/submit")->assertOk();

                return (int) $claim->id;
            },
            ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST => function (): int {
                Sanctum::actingAs($this->employeeUser);

                return (int) $this->postJson('/api/v1/portal/requests', [
                    'request_type_id' => $this->requestType->id,
                    'title' => 'W1 structural talep',
                    'priority' => 'normal',
                ])->assertCreated()->json('data.id');
            },
            ApprovalWorkflow::ENTITY_SALARY_REVIEW => function (): int {
                Sanctum::actingAs($this->admin);
                $periodId = (int) $this->postJson('/api/v1/salary-reviews', [
                    'name' => 'W1 Structural Zam',
                    'scope_type' => 'company',
                    'effective_date' => now()->toDateString(),
                ])->assertCreated()->json('data.id');

                $period = SalaryReviewPeriod::with('items')->findOrFail($periodId);
                foreach ($period->items as $item) {
                    $this->putJson("/api/v1/salary-reviews/{$periodId}/items/{$item->id}", [
                        'proposed_amount' => ((float) $item->current_amount) + 1000,
                        'change_reason' => 'annual_raise',
                    ])->assertOk();
                }

                $this->postJson("/api/v1/salary-reviews/{$periodId}/submit")->assertOk();

                return $periodId;
            },
            ApprovalWorkflow::ENTITY_DATA_SUBJECT_REQUEST => function (): int {
                Sanctum::actingAs($this->admin);

                return (int) $this->postJson('/api/v1/kvkk/data-subject-requests', [
                    'subject_type' => 'employee',
                    'subject_id' => $this->employee->id,
                    'applicant_name' => 'W1 Structural',
                    'contact' => 'w1-dsr@demo.test',
                    'request_types' => ['tasinabilirlik'],
                    'description' => 'W1 structural DSR',
                    'channel' => 'written',
                    'identity_verified' => true,
                ])->assertCreated()->json('data.id');
            },
        ];
    }
}
