<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D1c — Pivot + drill + ölçü kütüphanesi.
 */
class ReportPivotAndMeasureTest extends TestCase
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

        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'P1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'hire_date' => '2024-01-15',
            'gross_salary' => 10000,
            'position' => 'Dev',
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'P2',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'hire_date' => '2024-02-20',
            'gross_salary' => 20000,
            'position' => 'QA',
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'P3',
            'status' => 'passive',
            'department_id' => $this->deptB->id,
            'hire_date' => '2023-06-01',
            'gross_salary' => 15000,
            'position' => 'Dev',
        ]);
    }

    public function test_pivot_matrix_and_subtotals(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $res = $this->postJson('/api/v1/reports/pivot', [
            'dataset' => 'employees',
            'rows' => [['field' => 'department_name']],
            'columns' => [['field' => 'status']],
            'measures' => [
                ['fn' => 'count', 'field' => '*', 'alias' => 'adet'],
            ],
            'subtotals' => true,
            'grand_total' => true,
        ])->assertOk()->json('data');

        $this->assertNotEmpty($res['row_keys']);
        $this->assertNotEmpty($res['column_keys']);
        $this->assertNotEmpty($res['cells']);
        $this->assertArrayHasKey('grand_total', $res);
        $this->assertArrayHasKey('subtotals', $res);
    }

    public function test_pivot_date_grain(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/pivot', [
            'dataset' => 'employees',
            'rows' => [['field' => 'hire_date', 'grain' => 'year']],
            'columns' => [],
            'measures' => [['fn' => 'count', 'field' => '*', 'alias' => 'adet']],
        ])->assertOk()->assertJsonPath('data.row_headers.0.grain', 'year');
    }

    public function test_pivot_cardinality_guard(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        // employee_code as column with few values is fine; force guard by mocking via many statuses
        // Create >100 distinct statuses is heavy — use employee_code as column (3 values OK).
        // Instead call with a high-cardinality field after inserting many codes.
        for ($i = 0; $i < 105; $i++) {
            Employee::create([
                'company_id' => $this->company->id,
                'employee_code' => 'C'.$i,
                'status' => 'active',
                'department_id' => $this->deptA->id,
            ]);
        }

        $this->postJson('/api/v1/reports/pivot', [
            'dataset' => 'employees',
            'rows' => [['field' => 'status']],
            'columns' => [['field' => 'employee_code']],
            'measures' => [['fn' => 'count', 'field' => '*', 'alias' => 'adet']],
        ])->assertStatus(422);
    }

    public function test_drill_details_drops_salary_without_permission(): void
    {
        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('pivot_viewer', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo([
            'reports.definitions.run',
            'reports.definitions.view',
            'employees.list.view',
        ]);
        $viewer->assignRole($role);

        Sanctum::actingAs($viewer->fresh());
        $data = $this->postJson('/api/v1/reports/drill', [
            'dataset' => 'employees',
            'mode' => 'details',
            'cell_filters' => [['field' => 'status', 'value' => 'active']],
            'fields' => ['employee_code', 'gross_salary', 'status'],
        ])->assertOk()->json('data');

        $this->assertNotContains('gross_salary', $data['meta']['fields']);
    }

    public function test_drill_details_respects_department_scope(): void
    {
        $mgr = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $mgr->id,
            'employee_code' => 'MGR2',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        $role = Role::findOrCreate('dept_driller', 'sanctum');
        $role->forceFill(['data_scope' => 'department'])->save();
        $role->givePermissionTo(['reports.definitions.run', 'employees.list.view']);
        $mgr->assignRole($role);

        Sanctum::actingAs($mgr->fresh());
        $data = $this->postJson('/api/v1/reports/drill', [
            'dataset' => 'employees',
            'mode' => 'details',
            'fields' => ['employee_code', 'department_id'],
            'limit' => 100,
        ])->assertOk()->json('data');

        $codes = collect($data['rows'])->pluck('employee_code');
        $this->assertFalse($codes->contains('P3')); // dept B
    }

    public function test_measure_library_crud_and_tenant(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $create = $this->postJson('/api/v1/reports/measures', [
            'dataset_key' => 'employees',
            'key' => 'avg_gross',
            'label' => 'Ort. Brüt',
            'expression' => 'avg(gross_salary)',
            'format' => 'money',
            'decimals' => 2,
        ])->assertCreated()->json('data');

        $id = (int) $create['id'];
        $this->getJson('/api/v1/reports/measures?dataset_key=employees')->assertOk();

        $this->putJson("/api/v1/reports/measures/{$id}", [
            'label' => 'Ortalama Brüt',
        ])->assertOk()->assertJsonPath('data.label', 'Ortalama Brüt');

        // Diğer tenant erişemez
        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);
        Sanctum::actingAs($otherAdmin->fresh());
        $this->putJson("/api/v1/reports/measures/{$id}", ['label' => 'Hack'])->assertNotFound();
        $this->assertDatabaseHas('report_measures', [
            'id' => $id,
            'company_id' => $this->company->id,
            'label' => 'Ortalama Brüt',
        ]);

        Sanctum::actingAs($this->admin->fresh());
        $this->deleteJson("/api/v1/reports/measures/{$id}")->assertOk();
        $this->assertSoftDeleted('report_measures', ['id' => $id]);
    }

    public function test_measure_validate_rejects_injection_and_salary_leak(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/measures/validate', [
            'dataset' => 'employees',
            'expression' => 'sum(gross_salary); drop table employees',
        ])->assertStatus(422);

        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('expr_viewer', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo(['reports.definitions.run', 'employees.list.view']);
        $viewer->assignRole($role);

        Sanctum::actingAs($viewer->fresh());
        $this->postJson('/api/v1/reports/measures/validate', [
            'dataset' => 'employees',
            'expression' => 'sum(gross_salary)',
        ])->assertStatus(422);
    }

    public function test_pivot_expression_measure(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/pivot', [
            'dataset' => 'employees',
            'rows' => [['field' => 'status']],
            'columns' => [],
            'measures' => [
                [
                    'alias' => 'avg_sal',
                    'expression' => 'avg(gross_salary)',
                    'format' => 'money',
                ],
            ],
        ])->assertOk();
    }
}
