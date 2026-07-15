<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryRecord;
use App\Models\SalaryReviewPeriod;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SalaryReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $hr;

    private User $approver;

    private Employee $empA;

    private Employee $empB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);

        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();

        $this->hr = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->hr->assignRole('admin');
        $this->hr->givePermissionTo(['employees.salary.view', 'employees.salary.edit', 'employees.list.view']);
        $this->hr = $this->hr->fresh();

        $this->approver = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $this->approver->givePermissionTo(['employees.salary.view', 'employees.salary.edit']);
        $this->approver = $this->approver->fresh();

        $this->empA = Employee::factory()->create([
            'company_id' => $this->company->id,
            'gross_salary' => 40000,
            'currency' => 'TRY',
            'status' => 'active',
        ]);
        $this->empB = Employee::factory()->create([
            'company_id' => $this->company->id,
            'gross_salary' => 50000,
            'currency' => 'TRY',
            'status' => 'active',
        ]);

        $wf = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Zam Onayı',
            'entity_type' => ApprovalWorkflow::ENTITY_SALARY_REVIEW,
            'is_active' => true,
            'is_default' => true,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $wf->id,
            'step_order' => 1,
            'name' => 'İK',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $this->approver->id,
            'is_required' => true,
            'escalation_days' => 3,
        ]);
    }

    public function test_approve_applies_all_atomically(): void
    {
        Sanctum::actingAs($this->hr);
        $periodId = $this->postJson('/api/v1/salary-reviews', [
            'name' => '2026 Zam',
            'scope_type' => 'company',
            'effective_date' => now()->toDateString(),
        ])->assertStatus(201)->json('data.id');

        $period = SalaryReviewPeriod::with('items')->findOrFail($periodId);
        $this->assertGreaterThanOrEqual(2, $period->items->count());

        foreach ($period->items as $item) {
            $this->putJson("/api/v1/salary-reviews/{$periodId}/items/{$item->id}", [
                'proposed_amount' => ((float) $item->current_amount) + 5000,
                'change_reason' => 'annual_raise',
            ])->assertOk();
        }

        $this->postJson("/api/v1/salary-reviews/{$periodId}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/salary-reviews/{$periodId}/approve")->assertOk();

        $this->assertSame(SalaryReviewPeriod::STATUS_APPROVED, $period->fresh()->status);
        $this->assertEquals(45000.0, (float) $this->empA->fresh()->gross_salary);
        $this->assertEquals(55000.0, (float) $this->empB->fresh()->gross_salary);
        $this->assertSame(2, SalaryRecord::whereIn('employee_id', [$this->empA->id, $this->empB->id])
            ->where('change_reason', 'annual_raise')
            ->count());
    }

    public function test_reject_applies_nothing(): void
    {
        Sanctum::actingAs($this->hr);
        $periodId = $this->postJson('/api/v1/salary-reviews', [
            'name' => 'Red Test',
            'effective_date' => now()->toDateString(),
        ])->json('data.id');

        $period = SalaryReviewPeriod::with('items')->findOrFail($periodId);
        foreach ($period->items as $item) {
            $this->putJson("/api/v1/salary-reviews/{$periodId}/items/{$item->id}", [
                'proposed_amount' => 99999,
            ])->assertOk();
        }
        $this->postJson("/api/v1/salary-reviews/{$periodId}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/salary-reviews/{$periodId}/reject", [
            'reason' => 'Bütçe yok',
        ])->assertOk();

        $this->assertSame(SalaryReviewPeriod::STATUS_REJECTED, $period->fresh()->status);
        $this->assertEquals(40000.0, (float) $this->empA->fresh()->gross_salary);
        $this->assertEquals(50000.0, (float) $this->empB->fresh()->gross_salary);
    }
}
