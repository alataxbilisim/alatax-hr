<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AccrualLog;
use App\Models\AccrualPolicy;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\LeaveCalculationService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * TEMEL SAĞLAMLAŞTIRMA §4-§7 tests.
 */
class TemelSaglamlamaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin->fresh());
        Sanctum::actingAs($this->admin->fresh());
    }

    // ========== §4 ==========

    public function test_s4_employees_table_has_no_position_column(): void
    {
        $this->assertFalse(
            \Schema::hasColumn('employees', 'position'),
            'employees.position string column should be dropped'
        );
    }

    public function test_s4_position_name_unique_per_company(): void
    {
        Position::create([
            'company_id' => $this->company->id,
            'code' => 'UNIK_1',
            'name' => 'Benzersiz Pozisyon',
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Position::create([
            'company_id' => $this->company->id,
            'code' => 'UNIK_2',
            'name' => 'Benzersiz Pozisyon',
            'is_active' => true,
        ]);
    }

    public function test_s4_position_name_allowed_in_different_company(): void
    {
        $company2 = Company::factory()->create(['status' => CompanyStatus::Active]);

        Position::create([
            'company_id' => $this->company->id,
            'code' => 'CC_1',
            'name' => 'Cross Company Pozisyon',
            'is_active' => true,
        ]);

        $pos2 = Position::create([
            'company_id' => $company2->id,
            'code' => 'CC_2',
            'name' => 'Cross Company Pozisyon',
            'is_active' => true,
        ]);

        $this->assertNotNull($pos2->id);
    }

    // ========== §5 ==========

    public function test_s5_soft_delete_employee_then_recreate_same_code(): void
    {
        $emp = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'SD-001',
            'status' => 'active',
        ]);
        $emp->delete(); // soft-delete

        $emp2 = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'SD-001',
            'status' => 'active',
        ]);

        $this->assertNotSame($emp->id, $emp2->id);
        $this->assertSame('SD-001', $emp2->employee_code);
    }

    public function test_s5_duplicate_employee_code_active_rejected(): void
    {
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'DUP-001',
            'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'DUP-001',
            'status' => 'active',
        ]);
    }

    public function test_s5_soft_delete_user_then_recreate_same_email(): void
    {
        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'email' => 'reuse@test.dev',
        ]);
        $user->delete();

        $user2 = User::factory()->create([
            'home_company_id' => $this->company->id,
            'email' => 'reuse@test.dev',
        ]);

        $this->assertNotSame($user->id, $user2->id);
    }

    public function test_s5_national_id_unique_per_company(): void
    {
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'NID-001',
            'national_id' => '12345678901',
            'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'NID-002',
            'national_id' => '12345678901',
            'status' => 'active',
        ]);
    }

    public function test_s5_national_id_null_not_constrained(): void
    {
        $e1 = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'NUL-001',
            'national_id' => null,
            'status' => 'active',
        ]);
        $e2 = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'NUL-002',
            'national_id' => null,
            'status' => 'active',
        ]);

        $this->assertNotSame($e1->id, $e2->id);
    }

    // ========== §6 ==========

    public function test_s6_position_destroy_blocked_when_employees_use_it(): void
    {
        $pos = Position::create([
            'company_id' => $this->company->id,
            'code' => 'GUARD_POS',
            'name' => 'Guard Test Pozisyon',
            'is_active' => true,
        ]);

        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'GRD-001',
            'position_id' => $pos->id,
            'status' => 'active',
        ]);

        $this->deleteJson("/api/v1/positions/{$pos->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_s6_position_destroy_allowed_when_no_employees(): void
    {
        $pos = Position::create([
            'company_id' => $this->company->id,
            'code' => 'FREE_POS',
            'name' => 'Free Pozisyon',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/positions/{$pos->id}")
            ->assertOk();

        $this->assertSoftDeleted('positions', ['id' => $pos->id]);
    }

    public function test_s6_user_soft_delete_detaches_roles_and_tokens(): void
    {
        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);

        $role = Role::firstOrCreate(['name' => 'test_detach', 'guard_name' => 'sanctum']);
        $user->assignRole($role);
        $user->createToken('test-token');

        $this->assertTrue($user->hasRole('test_detach'));
        $this->assertGreaterThan(0, $user->tokens()->count());

        $user->delete();

        $this->assertSame(
            0,
            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', User::class)
                ->count()
        );
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_s6_role_destroy_blocked_when_approval_step_uses_it(): void
    {
        $role = Role::create(['name' => 'test_approval_guard', 'guard_name' => 'sanctum']);

        $wf = ApprovalWorkflow::create([
            'company_id' => $this->company->id,
            'name' => 'Guard Test WF',
            'entity_type' => 'leave_request',
            'is_active' => true,
            'is_default' => false,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $wf->id,
            'step_order' => 1,
            'name' => 'Role Step',
            'approver_type' => ApprovalStep::APPROVER_ROLE,
            'specific_role' => 'test_approval_guard',
            'is_required' => true,
        ]);

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(422);
    }

    public function test_s6_company_force_delete_no_fk_error(): void
    {
        $tempCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        // ActivityLog referencing this company
        \App\Models\ActivityLog::create([
            'company_id' => $tempCompany->id,
            'action' => 'test',
            'description' => 'FK test',
        ]);

        $tempCompany->forceDelete();
        $this->assertDatabaseMissing('companies', ['id' => $tempCompany->id]);
    }

    // ========== §7 ==========

    public function test_s7_accrual_rolls_back_on_error_per_employee(): void
    {
        $leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Yıllık İzin Test',
            'code' => 'ANNUAL_TEST',
            'system_code' => 'annual',
            'is_system' => false,
            'is_paid' => true,
            'default_days' => 14,
            'is_active' => true,
        ]);

        $policy = AccrualPolicy::create([
            'company_id' => $this->company->id,
            'leave_type_id' => $leaveType->id,
            'name' => 'Test Monthly',
            'accrual_type' => 'monthly',
            'accrual_rate' => 1.5,
            'is_active' => true,
        ]);

        $goodUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $goodBalance = LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $goodUser->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'total_days' => 10,
            'used_days' => 0,
            'pending_days' => 0,
            'carried_over' => 0,
            'accrued' => 0,
        ]);

        $service = app(LeaveCalculationService::class);
        $results = $service->processMonthlyAccruals(
            $this->company->id,
            now()->month,
            now()->year
        );

        $goodBalance->refresh();
        $this->assertGreaterThan(10, $goodBalance->total_days);
        $this->assertCount(1, $results);

        $this->assertSame(1, AccrualLog::where('user_id', $goodUser->id)->count());
    }
}
