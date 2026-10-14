<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * K4: KPI / grafik metrikleri — `age` ve `tenure` kolon değil, tarihten hesaplanır.
 */
class EmployeeDashboardKpiTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private const URL = '/api/v1/employees/dashboards/widget-data';

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createActiveCompany();
        foreach ([[30, 4, 50000], [40, 10, 70000]] as [$age, $tenure, $salary]) {
            Employee::factory()->active()->create([
                'company_id' => $this->company->id,
                'birth_date' => now()->subYears($age)->subDays(10)->toDateString(),
                'hire_date' => now()->subYears($tenure)->subDays(10)->toDateString(),
                'gross_salary' => $salary,
                'net_salary' => $salary * 0.7,
            ]);
        }

        $user = $this->userWithPermissions($this->company, ['employees.reports.view', 'employees.salary.view']);
        $this->actingAsInCompany($user, $this->company);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function measures(): array
    {
        return [
            'count' => ['count'],
            'salary' => ['salary'],
            'gross_salary' => ['gross_salary'],
            'net_salary' => ['net_salary'],
            'age' => ['age'],
            'tenure' => ['tenure'],
        ];
    }

    #[DataProvider('measures')]
    public function test_every_kpi_measure_returns_numeric_result(string $measure): void
    {
        $data = $this->postJson(self::URL, ['type' => 'kpi', 'config' => ['measure' => $measure]])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame(2, $data['total']);
        if ($measure !== 'count') {
            foreach (['average', 'min', 'max', 'sum'] as $key) {
                $this->assertIsNumeric($data[$key], "{$measure}.{$key}");
            }
        }
    }

    public function test_age_and_tenure_kpis_are_computed_from_dates(): void
    {
        $age = $this->postJson(self::URL, ['type' => 'kpi', 'config' => ['measure' => 'age']])->assertOk()->json('data');
        $this->assertEquals([35, 30, 40], [$age['average'], $age['min'], $age['max']]);

        $tenure = $this->postJson(self::URL, ['type' => 'kpi', 'config' => ['measure' => 'tenure']])->assertOk()->json('data');
        $this->assertEquals([7, 14], [$tenure['average'], $tenure['sum']]);
    }

    #[DataProvider('measures')]
    public function test_every_chart_measure_returns_numeric_values(string $measure): void
    {
        $rows = $this->postJson(self::URL, ['type' => 'chart', 'config' => ['dimension' => 'status', 'measure' => $measure]])
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertIsNumeric($row['value']);
        }
    }
}
