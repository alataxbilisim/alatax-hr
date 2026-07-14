<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /departments/managers — eksik Employee import 500'ünü kapatır.
 */
class DepartmentManagersApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->companyB = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->adminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminA);
        $this->adminA = $this->adminA->fresh();

        $this->adminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminB);
        $this->adminB = $this->adminB->fresh();
    }

    public function test_database_is_pgsql_testing(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('alatax_hr_testing', DB::connection()->getDatabaseName());
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/departments/managers')->assertStatus(401);
    }

    public function test_forbidden_without_departments_permission(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        $role = \App\Models\Role::findOrCreate('viewer_no_dept', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->syncPermissions(['employees.list.view']);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/departments/managers')->assertStatus(403);
    }

    public function test_returns_active_managers_for_company(): void
    {
        Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
            'employee_code' => 'MGR-A1',
        ]);
        Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'terminated',
            'employee_code' => 'MGR-INACTIVE',
        ]);
        Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'status' => 'active',
            'employee_code' => 'MGR-B1',
        ]);

        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/v1/departments/managers');
        $response->assertStatus(200)->assertJsonPath('success', true);

        $codes = collect($response->json('data'))->pluck('employee_code')->all();
        $this->assertContains('MGR-A1', $codes);
        $this->assertNotContains('MGR-INACTIVE', $codes);
        $this->assertNotContains('MGR-B1', $codes);
    }
}
