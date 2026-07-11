<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 2 Alan Seviyesi İzinler Dalga 1 — Employee hassas alanlar.
 */
class EmployeeFieldPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'employees.list.view',
            'employees.list.edit',
            'employees.list.create',
            'employees.list.delete',
            'employees.reports.view',
            'employees.*',
            'employees.salary.view',
            'employees.salary.edit',
            'employees.tckn.view',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        // Alan testi: hr_specialist maaş izni yok ama kayıt görebilmeli (department boşluğu olmasın)
        $specialistRole = Role::findByName('hr_specialist', 'sanctum');
        $specialistRole->data_scope = 'company';
        $specialistRole->save();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function userWithRole(string $role, array $permissions = [], ?UserType $type = null): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => $type ?? UserType::User,
        ]);
        $user->assignRole($role);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    private function employeeWithSensitive(array $attrs = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'gross_salary' => 50000,
            'net_salary' => 40000,
            'bank_name' => 'Test Bank',
            'iban' => 'TR000000000000000000000000',
            'sgk_number' => 'SGK123',
            'national_id' => '12345678901',
            'status' => 'active',
        ], $attrs));
    }

    public function test_hr_manager_sees_salary_in_show(): void
    {
        $hr = $this->userWithRole('hr_manager', ['employees.*']);
        $emp = $this->employeeWithSensitive();

        Sanctum::actingAs($hr);
        $response = $this->getJson("/api/v1/employees/{$emp->id}");

        $response->assertStatus(200);
        $data = $response->json('data.employee');
        $this->assertArrayHasKey('gross_salary', $data);
        $this->assertEquals(50000, (float) $data['gross_salary']);
        $this->assertArrayHasKey('iban', $data);
        $this->assertArrayHasKey('national_id', $data);
        $this->assertEquals('12345678901', $data['national_id']);
    }

    public function test_hr_specialist_salary_key_absent(): void
    {
        $specialist = $this->userWithRole('hr_specialist', [
            'employees.list.view',
            'employees.list.edit',
        ]);
        $emp = $this->employeeWithSensitive();

        Sanctum::actingAs($specialist);
        $response = $this->getJson("/api/v1/employees/{$emp->id}");

        $response->assertStatus(200);
        $data = $response->json('data.employee');
        $this->assertArrayNotHasKey('gross_salary', $data);
        $this->assertArrayNotHasKey('net_salary', $data);
        $this->assertArrayNotHasKey('iban', $data);
        $this->assertArrayNotHasKey('bank_name', $data);
        $this->assertArrayNotHasKey('sgk_number', $data);
        $this->assertArrayNotHasKey('national_id', $data);
        $this->assertArrayHasKey('employee_code', $data);
    }

    public function test_own_employee_sees_tckn_not_salary(): void
    {
        $user = $this->userWithRole('employee', ['employees.list.view']);
        $emp = $this->employeeWithSensitive(['user_id' => $user->id]);

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/v1/employees/{$emp->id}");

        $response->assertStatus(200);
        $data = $response->json('data.employee');
        $this->assertArrayHasKey('national_id', $data);
        $this->assertEquals('12345678901', $data['national_id']);
        $this->assertArrayNotHasKey('gross_salary', $data);
        $this->assertArrayNotHasKey('iban', $data);
    }

    public function test_manager_subordinate_no_salary_or_tckn(): void
    {
        $managerUser = $this->userWithRole('manager', ['employees.list.view']);
        $managerEmp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $managerUser->id,
            'status' => 'active',
        ]);
        $sub = $this->employeeWithSensitive(['manager_id' => $managerEmp->id]);

        Sanctum::actingAs($managerUser);
        $response = $this->getJson("/api/v1/employees/{$sub->id}");

        $response->assertStatus(200);
        $data = $response->json('data.employee');
        $this->assertArrayNotHasKey('gross_salary', $data);
        $this->assertArrayNotHasKey('national_id', $data);
        $this->assertArrayNotHasKey('iban', $data);
    }

    public function test_hr_specialist_write_salary_stripped_and_audit_note(): void
    {
        $specialist = $this->userWithRole('hr_specialist', [
            'employees.list.view',
            'employees.list.edit',
        ]);
        $emp = $this->employeeWithSensitive(['gross_salary' => 50000, 'title' => 'Eski']);

        Sanctum::actingAs($specialist);
        $response = $this->putJson("/api/v1/employees/{$emp->id}", [
            'gross_salary' => 99999,
            'net_salary' => 88888,
            'iban' => 'TR999',
            'national_id' => '99999999999',
            'title' => 'Yeni Unvan',
        ]);

        $response->assertStatus(200);
        $emp->refresh();
        $this->assertEquals(50000, (float) $emp->gross_salary);
        $this->assertEquals('12345678901', $emp->national_id);
        $this->assertEquals('Yeni Unvan', $emp->title);

        $this->assertTrue(
            ActivityLog::where('description', 'like', '%yetkisiz alan güncellemesi yok sayıldı%')
                ->where('description', 'like', '%gross_salary%')
                ->exists()
        );

        $data = $response->json('data');
        $this->assertArrayNotHasKey('gross_salary', $data);
    }

    public function test_company_admin_sees_all_sensitive_fields(): void
    {
        $admin = $this->userWithRole('admin', [], UserType::CompanyAdmin);
        $emp = $this->employeeWithSensitive();

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/v1/employees/{$emp->id}");

        $response->assertStatus(200);
        $data = $response->json('data.employee');
        $this->assertArrayHasKey('gross_salary', $data);
        $this->assertArrayHasKey('national_id', $data);
        $this->assertArrayHasKey('iban', $data);
    }

    public function test_hr_manager_salary_update_is_masked_in_audit(): void
    {
        $hr = $this->userWithRole('hr_manager', [
            'employees.*',
            'employees.salary.view',
            'employees.salary.edit',
            'employees.tckn.view',
        ]);
        $emp = $this->employeeWithSensitive(['gross_salary' => 50000]);

        Sanctum::actingAs($hr);
        $this->putJson("/api/v1/employees/{$emp->id}", [
            'gross_salary' => 60000,
        ])->assertStatus(200);

        $log = ActivityLog::where('model_id', $emp->id)
            ->where('action', 'update')
            ->where('description', 'like', '%maaş güncellendi%')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('*** güncellendi', $log->old_values['gross_salary'] ?? null);
        $this->assertEquals('*** güncellendi', $log->new_values['gross_salary'] ?? null);
        $this->assertStringNotContainsString('60000', json_encode($log->new_values));
        $this->assertEquals(Employee::class, $log->model_type);
    }

    public function test_dashboard_salary_widget_forbidden_without_permission(): void
    {
        $specialist = $this->userWithRole('hr_specialist', [
            'employees.list.view',
            'employees.reports.view',
        ]);

        Sanctum::actingAs($specialist);
        $this->postJson('/api/v1/employees/dashboards/widget-data', [
            'type' => 'kpi',
            'config' => [
                'measure' => 'gross_salary',
            ],
        ])->assertStatus(403);
    }

    public function test_report_salary_measure_forbidden_without_permission(): void
    {
        $specialist = $this->userWithRole('hr_specialist', [
            'employees.list.view',
            'employees.reports.view',
        ]);

        Sanctum::actingAs($specialist);
        $this->postJson('/api/v1/employees/reports/data', [
            'dimension' => 'department',
            'measure' => 'avg_gross_salary',
        ])->assertStatus(403);
    }
}
