<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\OnboardingProcess;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Services\DefaultCompanyHrSeedService;
use App\Services\Onboarding\DefaultOffboardingTemplateService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Offboarding Z2–Z3: sihirbaz, akıllı görevler, finalize/iptal, ibraname.
 */
class OffboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $employeeUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        foreach (['onboarding', 'assets', 'leave-management'] as $slug) {
            $mod = Module::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'is_core' => false, 'is_active' => true]
            );
            $this->company->modules()->syncWithoutDetaching([
                $mod->id => ['is_active' => true, 'activated_at' => now()],
            ]);
        }

        app(DefaultCompanyHrSeedService::class)->ensureForCompany($this->company);
        app(DefaultOffboardingTemplateService::class)->ensureForCompany($this->company);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->admin->givePermissionTo([
            'employees.terminate.create',
            'employees.list.view',
            'onboarding.processes.view',
            'onboarding.processes.edit',
            'onboarding.processes.create',
            'onboarding.templates.view',
        ]);

        $this->employeeUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'name' => 'Çıkış Adayı',
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'EMP-OFF-1',
            'status' => 'active',
            'hire_date' => now()->subYear()->toDateString(),
        ]);

        $annual = LeaveType::query()
            ->where('company_id', $this->company->id)
            ->where('system_code', 'annual')
            ->first();
        if ($annual) {
            LeaveBalance::create([
                'company_id' => $this->company->id,
                'user_id' => $this->employeeUser->id,
                'leave_type_id' => $annual->id,
                'year' => (int) now()->year,
                'total_days' => 14,
                'used_days' => 2,
                'pending_days' => 0,
                'carried_over' => 0,
            ]);
        }
    }

    public function test_start_keeps_employee_active_and_creates_tasks(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '03',
            'termination_date' => now()->addDays(7)->toDateString(),
            'exit_notes' => 'Test çıkış',
        ])->assertStatus(201);

        $this->assertSame(OnboardingProcess::TYPE_OFFBOARDING, $res->json('data.process_type'));
        $this->assertGreaterThanOrEqual(5, count($res->json('data.tasks')));
        $this->assertEquals(12.0, (float) $res->json('data.remaining_leave_days'));

        $this->employee->refresh();
        $this->assertSame('active', $this->employee->status);
    }

    public function test_open_asset_blocks_asset_return_task(): void
    {
        Sanctum::actingAs($this->admin);

        $category = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Laptop',
            'is_active' => true,
        ]);
        $asset = Asset::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'name' => 'MacBook',
            'asset_code' => 'AST-1',
            'status' => AssetStatus::Assigned,
        ]);
        AssetAssignment::create([
            'asset_id' => $asset->id,
            'user_id' => $this->employeeUser->id,
            'assigned_date' => now()->toDateString(),
            'assigned_by' => $this->admin->id,
        ]);

        $processId = $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '03',
            'termination_date' => now()->toDateString(),
        ])->assertStatus(201)->json('data.id');

        $task = OnboardingTask::query()
            ->where('process_id', $processId)
            ->get()
            ->first(fn (OnboardingTask $t) => ($t->data['action_key'] ?? null) === 'asset_return');

        $this->assertNotNull($task);
        $this->assertGreaterThan(0, $task->data['open_count'] ?? 0);

        $this->postJson("/api/v1/onboarding/processes/{$processId}/tasks/{$task->id}/complete", [])
            ->assertStatus(422);

        $task->refresh();
        $this->assertSame(OnboardingTask::STATUS_PENDING, $task->status);
    }

    public function test_revoke_portal_task_deactivates_user(): void
    {
        Sanctum::actingAs($this->admin);

        $processId = $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '04',
            'termination_date' => now()->toDateString(),
        ])->json('data.id');

        $task = OnboardingTask::query()
            ->where('process_id', $processId)
            ->get()
            ->first(fn (OnboardingTask $t) => ($t->data['action_key'] ?? null) === 'revoke_portal');

        $this->assertNotNull($task);
        $this->postJson("/api/v1/onboarding/processes/{$processId}/tasks/{$task->id}/complete", [])
            ->assertOk();

        $this->employeeUser->refresh();
        $this->employee->refresh();
        $this->assertFalse((bool) $this->employeeUser->is_active);
        $this->assertNull($this->employee->user_id);
        $this->assertSame('active', $this->employee->status);
    }

    public function test_finalize_requires_all_tasks_then_terminates(): void
    {
        Sanctum::actingAs($this->admin);

        $processId = $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '05',
            'termination_date' => now()->toDateString(),
        ])->json('data.id');

        $this->postJson("/api/v1/onboarding/processes/{$processId}/finalize-offboarding")
            ->assertStatus(422);

        $tasks = OnboardingTask::query()->where('process_id', $processId)->get();
        foreach ($tasks as $task) {
            if (($task->data['action_key'] ?? null) === 'asset_return') {
                // açık zimmet yok → tamamlanabilir
            }
            $this->postJson("/api/v1/onboarding/processes/{$processId}/tasks/{$task->id}/complete", [])
                ->assertOk();
        }

        $this->postJson("/api/v1/onboarding/processes/{$processId}/finalize-offboarding")
            ->assertOk()
            ->assertJsonPath('data.status', OnboardingProcess::STATUS_COMPLETED);

        $this->employee->refresh();
        $this->assertSame('terminated', $this->employee->status);
        $this->assertSame('05', $this->employee->termination_reason);
    }

    public function test_cancel_keeps_employee_active(): void
    {
        Sanctum::actingAs($this->admin);

        $processId = $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '22',
            'termination_date' => now()->toDateString(),
        ])->json('data.id');

        $this->postJson("/api/v1/onboarding/processes/{$processId}/cancel-offboarding")
            ->assertOk()
            ->assertJsonPath('data.status', OnboardingProcess::STATUS_CANCELLED);

        $this->employee->refresh();
        $this->assertSame('active', $this->employee->status);
    }

    public function test_terminate_permission_required(): void
    {
        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $viewer->givePermissionTo(['employees.list.view']);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '03',
            'termination_date' => now()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_tenant_isolation(): void
    {
        $other = Company::factory()->create(['status' => CompanyStatus::Active]);
        $otherAdmin = User::factory()->create([
            'company_id' => $other->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $otherAdmin->givePermissionTo(['employees.terminate.create']);
        Sanctum::actingAs($otherAdmin);

        $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '03',
            'termination_date' => now()->toDateString(),
        ])->assertStatus(404);
    }

    public function test_onboarding_regression_unaffected(): void
    {
        Sanctum::actingAs($this->admin);

        OnboardingTemplate::create([
            'company_id' => $this->company->id,
            'process_type' => OnboardingTemplate::TYPE_ONBOARDING,
            'name' => 'Giriş Şablonu',
            'tasks' => [['title' => 'Karşılama', 'type' => 'custom', 'is_required' => true]],
            'estimated_days' => 3,
            'is_active' => true,
            'is_default' => true,
        ]);

        $process = $this->postJson('/api/v1/onboarding/processes', [
            'user_id' => $this->employeeUser->id,
            'title' => 'Normal onboarding',
            'start_date' => now()->toDateString(),
            'template_id' => OnboardingTemplate::query()
                ->where('process_type', 'onboarding')
                ->where('name', 'Giriş Şablonu')
                ->value('id'),
        ])->assertStatus(201);

        $this->assertSame('onboarding', $process->json('data.process_type') ?? 'onboarding');
        // Default onboarding process_type
        $created = OnboardingProcess::find($process->json('data.id'));
        $this->assertSame(OnboardingProcess::TYPE_ONBOARDING, $created->process_type);
        $this->assertSame('active', $this->employee->fresh()->status);
    }

    public function test_clearance_form_pdf(): void
    {
        Sanctum::actingAs($this->admin);

        $processId = $this->postJson("/api/v1/employees/{$this->employee->id}/offboarding", [
            'termination_reason_code' => '11',
            'termination_date' => now()->toDateString(),
        ])->json('data.id');

        $this->get("/api/v1/onboarding/processes/{$processId}/clearance-form")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
