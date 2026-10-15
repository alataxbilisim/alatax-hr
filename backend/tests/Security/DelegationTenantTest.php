<?php

namespace Tests\Security;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\WorkflowService;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\CreatesOtherCompanyFixture;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * Adım 03B S2 — vekil yalnız aynı şirkette aktif üye olabilir; onay anında da yeniden doğrulanır.
 */
class DelegationTenantTest extends TestCase
{
    use CreatesOtherCompanyFixture;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private Company $company;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->company = $this->createActiveCompany();
        $this->manager = $this->userWithPermissions($this->company);
    }

    private function payload(int $delegateId): array
    {
        return [
            'delegate_id' => $delegateId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/approvals/delegations', $this->payload(1))->assertUnauthorized();
    }

    public function test_delegate_from_another_company_is_rejected(): void
    {
        $foreign = $this->userWithPermissions($this->otherCompany());

        $this->actingAsInCompany($this->manager, $this->company);
        $this->postJson('/api/v1/approvals/delegations', $this->payload($foreign->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('delegate_id');

        $this->assertSame(0, ApprovalDelegation::query()->count());
    }

    public function test_inactive_colleague_cannot_be_delegate(): void
    {
        $inactive = $this->userWithPermissions($this->company, [], ['is_active' => false]);

        $this->actingAsInCompany($this->manager, $this->company);
        $this->postJson('/api/v1/approvals/delegations', $this->payload($inactive->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('delegate_id');
    }

    public function test_active_colleague_can_be_delegate(): void
    {
        $colleague = $this->userWithPermissions($this->company);

        $this->actingAsInCompany($this->manager, $this->company);
        $this->postJson('/api/v1/approvals/delegations', $this->payload($colleague->id))
            ->assertCreated()
            ->assertJsonPath('data.delegate_id', $colleague->id);
    }

    public function test_delegate_moved_to_another_company_can_no_longer_approve(): void
    {
        $delegate = $this->userWithPermissions($this->company);
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Vekalet akışı',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
        ]);
        $step = ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Yönetici',
            'approver_type' => ApprovalStep::APPROVER_SPECIFIC_USER,
            'is_required' => true,
        ]);
        ApprovalDelegation::create([
            'company_id' => $this->company->id,
            'delegator_id' => $this->manager->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
            'created_by' => $this->manager->id,
        ]);
        $record = ApprovalRecord::create([
            'company_id' => $this->company->id,
            'approval_workflow_id' => $workflow->id,
            'approval_step_id' => $step->id,
            'approvable_type' => User::class,
            'approvable_id' => $this->manager->id,
            'step_order' => 1,
            'approver_id' => $this->manager->id,
            'status' => ApprovalRecord::STATUS_PENDING,
            'is_current' => true,
        ]);

        $workflowService = app(WorkflowService::class);
        $this->assertTrue($workflowService->canApprove($record, $delegate->id));
        $this->assertCount(1, $workflowService->getPendingApprovalsForUser($delegate->id, $this->company->id));

        $target = $this->otherCompany();
        $delegate->update(['home_company_id' => $target->id]);
        CompanyUser::query()->where('user_id', $delegate->id)->where('company_id', $this->company->id)->delete();

        $this->assertFalse($workflowService->canApprove($record->fresh(), $delegate->id));
        $this->assertCount(0, $workflowService->getPendingApprovalsForUser($delegate->id, $this->company->id));
        $this->assertNull(ApprovalDelegation::findActiveDelegate($this->manager->id, $this->company->id));
        $this->assertTrue($workflowService->canApprove($record->fresh(), $this->manager->id));
    }

    public function test_deactivated_delegate_can_no_longer_approve(): void
    {
        $delegate = $this->userWithPermissions($this->company);
        ApprovalDelegation::create([
            'company_id' => $this->company->id,
            'delegator_id' => $this->manager->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
            'created_by' => $this->manager->id,
        ]);

        $this->assertSame($delegate->id, ApprovalDelegation::findActiveDelegate($this->manager->id, $this->company->id)?->id);

        $delegate->update(['is_active' => false]);

        $this->assertNull(ApprovalDelegation::findActiveDelegate($this->manager->id, $this->company->id));
    }
}
