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
 * 4B-B5: Workflow yönetim API (yapılandırma) + editör-kurulu zincirin motor yürütmesi.
 */
class WorkflowManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'management.workflows.view',
            'management.workflows.create',
            'management.workflows.edit',
            'management.workflows.delete',
            'leaves.requests.view',
            'leaves.requests.create',
            'leaves.requests.approve',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'manager', 'employee', 'hr_manager'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        $adminRole = Role::findByName('admin', 'sanctum');
        $adminRole->syncPermissions([
            'management.workflows.view',
            'management.workflows.create',
            'management.workflows.edit',
            'management.workflows.delete',
        ]);

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $module = Module::firstOrCreate(
            ['slug' => 'leave-management'],
            ['name' => 'leave-management', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual B5',
            'code' => 'AN-B5',
            'is_active' => true,
            'default_days' => 30,
        ]);
    }

    public function test_index_unauthenticated_401(): void
    {
        $this->getJson('/api/v1/workflows')->assertStatus(401);
    }

    public function test_index_unauthorized_role_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $user->assignRole('employee');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/workflows')->assertStatus(403);
    }

    public function test_crud_happy_path_and_steps_sync(): void
    {
        Sanctum::actingAs($this->admin);

        $manager = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $gm = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);

        $create = $this->postJson('/api/v1/workflows', [
            'name' => 'Stüdyo Zinciri',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'escalation_days' => 5,
            'steps' => [
                [
                    'name' => 'Yönetici',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $manager->id,
                    'completion_policy' => 'all',
                ],
                [
                    'name' => 'GM',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $gm->id,
                    'condition' => ['field' => 'total_days', 'op' => '>', 'value' => 10],
                    'completion_policy' => 'all',
                ],
            ],
        ]);
        $create->assertStatus(201);
        $id = (int) $create->json('data.id');
        $this->assertCount(2, $create->json('data.steps'));

        $show = $this->getJson("/api/v1/workflows/{$id}")->assertStatus(200);
        $step1Id = (int) $show->json('data.steps.0.id');

        $update = $this->putJson("/api/v1/workflows/{$id}", [
            'name' => 'Stüdyo Zinciri v2',
            'escalation_days' => 7,
            'steps' => [
                [
                    'id' => $step1Id,
                    'name' => 'Yönetici',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $manager->id,
                ],
                [
                    'name' => 'Paralel A',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $manager->id,
                    'parallel_group' => 1,
                    'completion_policy' => 'any',
                ],
                [
                    'name' => 'Paralel B',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $gm->id,
                    'parallel_group' => 1,
                    'completion_policy' => 'any',
                ],
            ],
        ]);
        $update->assertStatus(200);
        $this->assertSame('Stüdyo Zinciri v2', $update->json('data.name'));
        $this->assertCount(3, $update->json('data.steps'));
        $this->assertSame($step1Id, (int) $update->json('data.steps.0.id'));
        $this->assertSame('any', $update->json('data.steps.1.completion_policy'));

        $this->deleteJson("/api/v1/workflows/{$id}")->assertStatus(200);
        $this->assertSoftDeleted('approval_workflows', ['id' => $id]);
    }

    public function test_tenant_isolation_other_company_404(): void
    {
        $wf = ApprovalWorkflow::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/workflows/{$wf->id}")->assertStatus(404);
        $this->putJson("/api/v1/workflows/{$wf->id}", ['name' => 'Hack'])->assertStatus(404);
    }

    public function test_open_instance_blocks_step_change_409(): void
    {
        Sanctum::actingAs($this->admin);

        $approver = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);

        $wf = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Locked',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $wf->id,
            'step_order' => 1,
            'name' => 'A',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $approver->id,
            'is_required' => true,
            'completion_policy' => 'all',
        ]);

        ApprovalInstance::create([
            'company_id' => $this->company->id,
            'approval_workflow_id' => $wf->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => 1,
            'current_step' => 1,
            'status' => ApprovalInstance::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $this->putJson("/api/v1/workflows/{$wf->id}", [
            'steps' => [
                [
                    'name' => 'Changed',
                    'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER,
                ],
            ],
        ])->assertStatus(409);

        // Metadata-only güncelleme serbest
        $this->putJson("/api/v1/workflows/{$wf->id}", [
            'name' => 'Locked renamed',
            'description' => 'meta ok',
        ])->assertStatus(200);
    }

    public function test_delete_blocked_when_instance_exists_409(): void
    {
        Sanctum::actingAs($this->admin);

        $wf = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Has instance',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => false,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $wf->id,
            'step_order' => 1,
            'name' => 'A',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER,
            'is_required' => true,
            'completion_policy' => 'all',
        ]);

        ApprovalInstance::create([
            'company_id' => $this->company->id,
            'approval_workflow_id' => $wf->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => 99,
            'current_step' => 1,
            'status' => ApprovalInstance::STATUS_APPROVED,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->deleteJson("/api/v1/workflows/{$wf->id}")->assertStatus(409);
    }

    public function test_seed_default_leave_idempotent(): void
    {
        Sanctum::actingAs($this->admin);

        $first = $this->postJson('/api/v1/workflows/seed-default-leave')->assertStatus(200);
        $second = $this->postJson('/api/v1/workflows/seed-default-leave')->assertStatus(200);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(ApprovalStep::APPROVER_DYNAMIC_MANAGER, $first->json('data.steps.0.approver_type'));
    }

    /**
     * ADIM 3: Editör API ile 2 adımlı + koşullu (gün>10) + paralel any → motor aynen yürütür.
     */
    public function test_editor_built_chain_executed_by_motor(): void
    {
        Sanctum::actingAs($this->admin);

        $manager = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $manager->assignRole('manager');
        $peerA = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $peerB = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $requester = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $requester->assignRole('employee');
        $requester->givePermissionTo(['leaves.requests.create', 'leaves.requests.view']);

        Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($peerA)->create();
        Employee::factory()->forUser($peerB)->create();
        Employee::factory()->forUser($requester)->create();

        $create = $this->postJson('/api/v1/workflows', [
            'name' => 'B5 E2E Zincir',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'steps' => [
                [
                    'name' => 'Direkt Yönetici',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $manager->id,
                    'completion_policy' => 'all',
                ],
                [
                    'name' => 'Paralel İK A',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $peerA->id,
                    'condition' => ['field' => 'total_days', 'op' => '>', 'value' => 10],
                    'parallel_group' => 1,
                    'completion_policy' => 'any',
                ],
                [
                    'name' => 'Paralel İK B',
                    'approver_type' => ApprovalStep::APPROVER_USER,
                    'specific_user_id' => $peerB->id,
                    'condition' => ['field' => 'total_days', 'op' => '>', 'value' => 10],
                    'parallel_group' => 1,
                    'completion_policy' => 'any',
                ],
            ],
        ])->assertStatus(201);

        $workflowId = (int) $create->json('data.id');
        $this->assertCount(3, $create->json('data.steps'));

        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 30,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        // Kısa izin: koşul false → yalnız adım 1
        $short = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(50)->toDateString(),
            'end_date' => now()->addDays(51)->toDateString(),
            'total_days' => 2,
            'reason' => 'B5 short',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveBalance::where('user_id', $requester->id)->first()?->addPending(2);

        $rec1 = app(WorkflowService::class)->startWorkflow($short, ['total_days' => 2]);
        $this->assertNotNull($rec1);
        $this->assertSame(1, ApprovalRecord::query()
            ->where('approvable_id', $short->id)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->where('is_current', true)
            ->count());

        // Uzun izin: adım1 sonra paralel any (2 pending)
        $long = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(60)->toDateString(),
            'end_date' => now()->addDays(75)->toDateString(),
            'total_days' => 12,
            'reason' => 'B5 long',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveBalance::where('user_id', $requester->id)->first()?->addPending(12);

        app(WorkflowService::class)->startWorkflow($long, ['total_days' => 12]);

        Sanctum::actingAs($manager);
        $manager->givePermissionTo(['leaves.requests.approve', 'leaves.requests.view']);
        $this->postJson("/api/v1/leaves/requests/{$long->id}/approve")->assertStatus(200);

        $parallelPending = ApprovalRecord::query()
            ->where('approvable_id', $long->id)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->where('is_current', true)
            ->count();
        $this->assertSame(2, $parallelPending, 'koşul sonrası paralel dalga');

        Sanctum::actingAs($peerA);
        $peerA->givePermissionTo(['leaves.requests.approve', 'leaves.requests.view']);
        $this->postJson("/api/v1/leaves/requests/{$long->id}/approve")->assertStatus(200);

        $long->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $long->status->value);
        $this->assertSame(
            $workflowId,
            (int) ApprovalInstance::query()->where('approvable_id', $long->id)->value('approval_workflow_id')
        );
    }
}
