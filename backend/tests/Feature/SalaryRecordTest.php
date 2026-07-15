<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Salary\SalaryRecordService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * B11 Z1: ücret geçmişi + strip + portal own-scope.
 */
class SalaryRecordTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $hr;

    private User $noSalary;

    private Employee $employee;

    private User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->hr = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $adminRole = \Spatie\Permission\Models\Role::findByName('admin', 'sanctum');
        $adminRole->forceFill(['data_scope' => 'company'])->save();
        $this->hr->assignRole($adminRole);
        $this->hr->givePermissionTo([
            'employees.list.view',
            'employees.salary.view',
            'employees.salary.edit',
        ]);
        $this->hr = $this->hr->fresh();

        $this->noSalary = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $specialist = \Spatie\Permission\Models\Role::findByName('hr_specialist', 'sanctum');
        $specialist->forceFill(['data_scope' => 'company'])->save();
        $this->noSalary->assignRole($specialist);
        $this->noSalary->givePermissionTo(['employees.list.view']);
        $this->noSalary = $this->noSalary->fresh();

        $this->portalUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->portalUser->id,
            'gross_salary' => 40000,
            'currency' => 'TRY',
            'status' => 'active',
            'hire_date' => now()->subYear()->toDateString(),
        ]);
    }

    public function test_without_salary_view_api_403_and_show_strips_salary(): void
    {
        Sanctum::actingAs($this->noSalary);

        $this->getJson("/api/v1/employees/{$this->employee->id}/salary")->assertStatus(403);

        $show = $this->getJson("/api/v1/employees/{$this->employee->id}")->assertOk();
        $payload = $show->json('data.employee') ?? $show->json('data');
        $this->assertArrayNotHasKey('gross_salary', $payload);
        $this->assertArrayNotHasKey('net_salary', $payload);
    }

    public function test_create_record_syncs_employee_and_masks_audit(): void
    {
        Sanctum::actingAs($this->hr);

        $this->postJson("/api/v1/employees/{$this->employee->id}/salary", [
            'effective_date' => now()->toDateString(),
            'amount' => 55000,
            'change_reason' => 'annual_raise',
            'note' => '2026 zam',
        ])->assertStatus(201);

        $this->employee->refresh();
        $this->assertEquals(55000.0, (float) $this->employee->gross_salary);

        $logs = ActivityLog::query()
            ->where('description', 'like', '%ücret%')
            ->latest('id')
            ->limit(5)
            ->get();

        $this->assertTrue($logs->isNotEmpty());
        foreach ($logs as $log) {
            $blob = json_encode($log->new_values).json_encode($log->old_values).$log->description;
            $this->assertStringNotContainsString('55000', (string) $blob);
            $this->assertStringNotContainsString('40000', (string) $blob);
        }
    }

    public function test_backfill_initial_idempotent(): void
    {
        $svc = app(SalaryRecordService::class);
        $a = $svc->ensureInitialFromEmployee($this->employee);
        $b = $svc->ensureInitialFromEmployee($this->employee);

        $this->assertNotNull($a);
        $this->assertNull($b);
        $this->assertSame(1, SalaryRecord::where('employee_id', $this->employee->id)->count());
        $this->assertSame('initial', $a->change_reason);
    }

    public function test_portal_own_salary_ok_other_forbidden(): void
    {
        Sanctum::actingAs($this->portalUser);

        $this->getJson('/api/v1/portal/salary')->assertOk()
            ->assertJsonPath('data.current.amount', '40000.00');

        $other = Employee::factory()->create([
            'company_id' => $this->company->id,
            'gross_salary' => 99999,
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/portal/salary/{$other->id}")->assertStatus(403);
    }

    public function test_tenant_isolation(): void
    {
        $otherCo = Company::factory()->create(['status' => CompanyStatus::Active]);
        $otherHr = User::factory()->create([
            'company_id' => $otherCo->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $otherHr->givePermissionTo(['employees.salary.view', 'employees.salary.edit']);
        Sanctum::actingAs($otherHr);

        $this->getJson("/api/v1/employees/{$this->employee->id}/salary")->assertStatus(404);
    }
}
