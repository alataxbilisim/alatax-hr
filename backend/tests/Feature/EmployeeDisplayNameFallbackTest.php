<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * K6: personel adı = employees.full_name; yoksa bağlı hesap adı; o da yoksa sicil no ("Unknown" değil).
 */
class EmployeeDisplayNameFallbackTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createActiveCompany();
    }

    public function test_display_name_prefers_full_name_then_user_then_employee_code(): void
    {
        $user = $this->userWithPermissions($this->company, [], ['name' => 'Hesap Adı']);

        $withFullName = Employee::factory()->forUser($user)->create(['full_name' => 'Kayıtlı Ad']);
        $userOnly = Employee::factory()->forUser($user)->create(['full_name' => null]);
        $orphan = Employee::factory()->terminated()->create([
            'company_id' => $this->company->id,
            'full_name' => '  ',
            'employee_code' => 'DEM-035',
        ]);

        $this->assertSame('Kayıtlı Ad', $withFullName->display_name);
        $this->assertSame('Hesap Adı', $userOnly->fresh()->display_name);
        $this->assertSame('DEM-035', $orphan->fresh()->display_name);
    }

    public function test_terminated_employee_without_account_is_listed_with_its_name(): void
    {
        Employee::factory()->terminated()->create([
            'company_id' => $this->company->id,
            'full_name' => 'Ayrılan Personel',
            'employee_code' => 'TRM-001',
        ]);
        Employee::factory()->terminated()->create([
            'company_id' => $this->company->id,
            'full_name' => null,
            'employee_code' => 'TRM-002',
        ]);

        $admin = $this->companyAdminFor($this->company, ['employees.list.view']);
        $this->actingAsInCompany($admin, $this->company);

        $rows = collect($this->getJson('/api/v1/employees?per_page=50')->assertOk()->json('data'))->keyBy('employee_code');

        $this->assertSame('Ayrılan Personel', $rows['TRM-001']['name']);
        $this->assertSame('TRM-002', $rows['TRM-002']['name']);
        $this->assertNotContains('Unknown', $rows->pluck('name')->all());
    }
}
