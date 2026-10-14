<?php

namespace Tests\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Module;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOtherCompanyFixture;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * K3: istemci kaynaklı kolon adları (filtre / sıralama) yalnızca beyaz listeden geçer.
 */
class QueryColumnWhitelistTest extends TestCase
{
    use CreatesOtherCompanyFixture;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private const WIDGET_URL = '/api/v1/employees/dashboards/widget-data';

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createActiveCompany();
        Employee::factory()->count(2)->active()->create(['company_id' => $this->company->id]);
        Employee::factory()->terminated()->create(['company_id' => $this->company->id]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function kpiPayload(array $filters): array
    {
        return ['type' => 'kpi', 'config' => ['measure' => 'count', 'filters' => $filters]];
    }

    public function test_widget_filter_with_unknown_field_returns_422(): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['employees.reports.view']), $this->company);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload(['password' => 'x']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['config.filters.password']);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload(['status = status OR 1=1 --' => 'x']))
            ->assertStatus(422);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sensitiveFilterFields(): array
    {
        return [
            'brüt maaş' => ['gross_salary'],
            'net maaş' => ['net_salary'],
            'tckn' => ['national_id'],
            'doğum tarihi' => ['birth_date'],
            'telefon' => ['personal_phone'],
        ];
    }

    #[DataProvider('sensitiveFilterFields')]
    public function test_widget_filter_on_sensitive_field_without_permission_returns_403(string $field): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['employees.reports.view']), $this->company);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload([$field => '1']))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_widget_salary_filter_with_salary_permission_is_allowed(): void
    {
        $user = $this->userWithPermissions($this->company, ['employees.reports.view', 'employees.salary.view']);
        $this->actingAsInCompany($user, $this->company);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload(['gross_salary' => '0']))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_widget_filter_on_allowed_field_filters_results(): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['employees.reports.view']), $this->company);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload(['status' => ['active']]))
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload(['status' => 'terminated']))
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_widget_filter_with_nested_value_returns_422(): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['employees.reports.view']), $this->company);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload(['status' => ['a' => ['b' => 'c']]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['config.filters.status']);
    }

    public function test_widget_filter_cannot_reach_other_company_records(): void
    {
        $foreign = $this->otherCompanyEmployee();
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['employees.reports.view']), $this->company);

        $this->postJson(self::WIDGET_URL, $this->kpiPayload(['id' => $foreign->id]))
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_employee_list_sorting_is_whitelisted(): void
    {
        $this->actingAsInCompany($this->companyAdminFor($this->company, ['employees.list.view', 'employees.salary.view']), $this->company);

        $this->getJson('/api/v1/employees?sort_by=gross_salary')->assertStatus(422)->assertJsonValidationErrors(['sort_by']);
        $this->getJson('/api/v1/employees?sort_by=national_id')->assertStatus(422);
        $this->getJson('/api/v1/employees?sort_by=full_name&sort_order=sideways')->assertStatus(422)->assertJsonValidationErrors(['sort_order']);
        $this->getJson('/api/v1/employees?sort_by=full_name&sort_order=asc')->assertOk();
        $this->getJson('/api/v1/employees')->assertOk();
    }

    public function test_company_user_list_sorting_is_whitelisted(): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['management.users.view']), $this->company);

        $this->getJson('/api/v1/users?sort_by=password')->assertStatus(422)->assertJsonValidationErrors(['sort_by']);
        $this->getJson('/api/v1/users?sort_by=two_factor_secret')->assertStatus(422);
        $this->getJson('/api/v1/users?sort_by=name&sort_dir=ASC')->assertOk();
    }

    public function test_job_position_list_sorting_is_whitelisted(): void
    {
        $module = Module::firstOrCreate(['slug' => 'job-applications'], ['name' => 'job-applications', 'is_core' => false, 'is_active' => true]);
        $this->company->modules()->syncWithoutDetaching([$module->id => ['is_active' => true, 'activated_at' => now()]]);
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['recruitment.positions.view']), $this->company);

        $this->getJson('/api/v1/recruitment/positions?sort_by=salary_min')->assertStatus(422);
        $this->getJson('/api/v1/recruitment/positions?sort_by=title&sort_dir=asc')->assertOk();
    }

    public function test_super_admin_list_sorting_is_whitelisted(): void
    {
        Sanctum::actingAs($this->superAdminUser());

        $this->getJson('/api/v1/admin/companies?sort_by=current_balance')->assertStatus(422);
        $this->getJson('/api/v1/admin/companies?sort_by=name&sort_dir=asc')->assertOk();
        $this->getJson('/api/v1/admin/users?sort_by=password')->assertStatus(422);
        $this->getJson('/api/v1/admin/users?sort_by=email')->assertOk();
    }
}
