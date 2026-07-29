<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\ReportQueryBuilder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D1a — Rapor motoru güvenlik: alan izni, DataScope, tenant, enjeksiyon.
 */
class ReportEngineSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    private Department $deptA;

    private Department $deptB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->deptA = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept A',
            'code' => 'DA',
            'is_active' => true,
        ]);
        $this->deptB = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept B',
            'code' => 'DB',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();
    }

    public function test_salary_field_dropped_without_permission(): void
    {
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'E1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'gross_salary' => 50000,
            'net_salary' => 40000,
        ]);

        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('report_viewer', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
            'employees.list.view',
        ]);
        $viewer->assignRole($role);

        Sanctum::actingAs($viewer->fresh());
        $catalog = $this->getJson('/api/v1/reports/datasets')->assertOk()->json('data');
        $employees = collect($catalog)->firstWhere('key', 'employees');
        $keys = collect($employees['fields'])->pluck('key');
        $this->assertFalse($keys->contains('gross_salary'));
        $this->assertFalse($keys->contains('net_salary'));

        $preview = $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'gross_salary', 'status'],
        ])->assertOk()->json('data');

        $this->assertContains('employee_code', $preview['meta']['fields']);
        $this->assertNotContains('gross_salary', $preview['meta']['fields']);
        if (($preview['rows'][0] ?? null) !== null) {
            $this->assertArrayNotHasKey('gross_salary', $preview['rows'][0]);
        }
    }

    public function test_salary_sum_blocked_without_permission(): void
    {
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'E2',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'gross_salary' => 50000,
        ]);

        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('report_viewer2', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo(['reports.definitions.run', 'employees.list.view']);
        $viewer->assignRole($role);

        Sanctum::actingAs($viewer->fresh());
        $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['department_id'],
            'group_by' => ['department_id'],
            'aggregations' => [
                ['field' => 'gross_salary', 'fn' => 'sum', 'alias' => 'sum_salary'],
            ],
        ])->assertStatus(422);
    }

    public function test_department_scope_hides_other_department_rows(): void
    {
        $mgrUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $mgrUser->id,
            'employee_code' => 'MGR',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'IN-A',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'IN-B',
            'status' => 'active',
            'department_id' => $this->deptB->id,
        ]);

        $role = Role::findOrCreate('dept_reporter', 'sanctum');
        $role->forceFill(['data_scope' => 'department'])->save();
        $role->givePermissionTo(['reports.definitions.run', 'employees.list.view']);
        $mgrUser->assignRole($role);

        Sanctum::actingAs($mgrUser->fresh());
        $result = $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'department_id'],
            'limit' => 100,
        ])->assertOk()->json('data');

        $codes = collect($result['rows'])->pluck('employee_code');
        $this->assertTrue($codes->contains('IN-A'));
        $this->assertTrue($codes->contains('MGR'));
        $this->assertFalse($codes->contains('IN-B'));
    }

    public function test_tenant_isolation_on_preview(): void
    {
        Employee::create([
            'company_id' => $this->otherCompany->id,
            'employee_code' => 'OTHER',
            'status' => 'active',
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'MINE',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);

        Sanctum::actingAs($this->admin->fresh());
        $result = $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code'],
        ])->assertOk()->json('data');

        $codes = collect($result['rows'])->pluck('employee_code');
        $this->assertTrue($codes->contains('MINE'));
        $this->assertFalse($codes->contains('OTHER'));
    }

    public function test_column_injection_rejected(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code; DROP TABLE employees;--'],
        ])->assertStatus(422);

        $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code'],
            'filters' => [
                ['field' => 'status;delete', 'op' => 'eq', 'value' => 'active'],
            ],
        ])->assertStatus(422);
    }

    public function test_operator_and_join_injection_rejected(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code'],
            'filters' => [
                ['field' => 'status', 'op' => 'eq;drop', 'value' => 'x'],
            ],
        ])->assertStatus(422);

        $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code'],
            'joins' => ['users; drop table users'],
        ])->assertOk(); // bilinmeyen join sessizce yok sayılır

        /** @var ReportQueryBuilder $builder */
        $builder = app(ReportQueryBuilder::class);
        $this->expectException(\InvalidArgumentException::class);
        $builder->run($this->admin, (int) $this->company->id, [
            'dataset' => 'employees',
            'fields' => ['employee_code'],
            'filters' => [
                ['field' => 'status', 'op' => 'union select', 'value' => 1],
            ],
        ]);
    }

    public function test_custom_field_appears_and_filters(): void
    {
        CustomFieldDefinition::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'entity_type' => CustomFieldDefinition::ENTITY_EMPLOYEE,
            'field_key' => 'badge_color',
            'field_label' => 'Rozet Rengi',
            'field_type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 10,
        ]);

        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'CF-1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'custom_fields' => ['badge_color' => 'blue'],
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'CF-2',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'custom_fields' => ['badge_color' => 'red'],
        ]);

        Sanctum::actingAs($this->admin->fresh());
        $catalog = $this->getJson('/api/v1/reports/datasets')->assertOk()->json('data');
        $employees = collect($catalog)->firstWhere('key', 'employees');
        $keys = collect($employees['fields'])->pluck('key');
        $this->assertTrue($keys->contains('cf_badge_color'));

        $result = $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'cf_badge_color'],
            'filters' => [
                ['field' => 'cf_badge_color', 'op' => 'eq', 'value' => 'blue'],
            ],
        ])->assertOk()->json('data');

        $codes = collect($result['rows'])->pluck('employee_code');
        $this->assertTrue($codes->contains('CF-1'));
        $this->assertFalse($codes->contains('CF-2'));
    }

    public function test_raw_sql_not_executed_via_dataset_key(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $before = DB::selectOne('select count(*)::int as c from employees')->c;

        $this->postJson('/api/v1/reports/preview', [
            'dataset' => "employees; delete from employees;--",
            'fields' => ['employee_code'],
        ])->assertStatus(422);

        $after = DB::selectOne('select count(*)::int as c from employees')->c;
        $this->assertSame($before, $after);
    }

    /** D1b — export da maaş alanını düşürmeli */
    public function test_export_drops_salary_without_permission(): void
    {
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EX1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'gross_salary' => 99000,
            'net_salary' => 70000,
        ]);

        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('report_export_viewer', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
            'employees.list.view',
        ]);
        $viewer->assignRole($role);

        Sanctum::actingAs($viewer->fresh());
        $export = $this->postJson('/api/v1/reports/export', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'gross_salary', 'net_salary', 'status'],
        ])->assertOk()->json('data');

        $this->assertNotContains('gross_salary', $export['meta']['fields']);
        $this->assertNotContains('net_salary', $export['meta']['fields']);
        if (($export['rows'][0] ?? null) !== null) {
            $this->assertArrayNotHasKey('gross_salary', $export['rows'][0]);
            $this->assertArrayNotHasKey('net_salary', $export['rows'][0]);
        }
    }

    /** D1b — export DataScope: başka departman satırı gelmez */
    public function test_export_respects_department_scope(): void
    {
        $mgrUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $mgrUser->id,
            'employee_code' => 'EX-MGR',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EX-A',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EX-B',
            'status' => 'active',
            'department_id' => $this->deptB->id,
        ]);

        $role = Role::findOrCreate('dept_exporter', 'sanctum');
        $role->forceFill(['data_scope' => 'department'])->save();
        $role->givePermissionTo(['reports.definitions.run', 'employees.list.view']);
        $mgrUser->assignRole($role);

        Sanctum::actingAs($mgrUser->fresh());
        $result = $this->postJson('/api/v1/reports/export', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'department_id'],
        ])->assertOk()->json('data');

        $codes = collect($result['rows'])->pluck('employee_code');
        $this->assertTrue($codes->contains('EX-A'));
        $this->assertTrue($codes->contains('EX-MGR'));
        $this->assertFalse($codes->contains('EX-B'));
        $this->assertArrayHasKey('truncated', $result['meta']);
    }
}
