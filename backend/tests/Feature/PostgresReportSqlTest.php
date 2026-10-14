<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Adım 01 — eski modül rapor uçlarındaki MySQL'e özgü SQL PostgreSQL'e çevrildi;
 * her uç gerçek PostgreSQL üzerinde 200 + beklenen JSON şekliyle doğrulanır.
 */
class PostgresReportSqlTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->enableModule($this->company, 'job-applications');
        $this->enableModule($this->company, 'document-management');

        $admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->admin = $this->assignSpatieAdminRole($admin);

        Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'gender' => 'male',
            'birth_date' => now()->subYears(30)->subDays(10)->toDateString(),
            'hire_date' => now()->subYears(2)->subDays(10)->toDateString(),
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'gender' => null,
            'birth_date' => now()->subYears(50)->subDays(10)->toDateString(),
            'hire_date' => now()->subMonths(3)->toDateString(),
        ]);
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_core' => false, 'is_active' => true]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);
    }

    public function test_employee_stats_age_seniority_and_fallback_labels(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/employees/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'total_employees', 'active_employees',
                'by_department' => [['name', 'count']],
                'by_gender' => [['gender', 'count']],
                'by_age_group' => [['name', 'count']],
                'by_seniority' => [['range', 'count']],
                'recent_hires', 'recent_terminations',
            ]]);

        $data = $response->json('data');
        $this->assertContains(['name' => 'Departman Atanmamış', 'count' => 2], $data['by_department']);
        $this->assertContains(['gender' => 'unspecified', 'count' => 1], $data['by_gender']);
        $this->assertContains(['name' => '25-34', 'count' => 1], $data['by_age_group']);
        $this->assertContains(['name' => '45-54', 'count' => 1], $data['by_age_group']);
        $this->assertContains(['range' => '1-3 Yıl', 'count' => 1], $data['by_seniority']);
        $this->assertContains(['range' => '0-1 Yıl', 'count' => 1], $data['by_seniority']);
    }

    public function test_employee_report_data_date_dimensions_and_age_measures(): void
    {
        Sanctum::actingAs($this->admin);

        $cases = [
            ['hire_year', 'count'],
            ['hire_month', 'count'],
            ['age_group', 'count'],
            ['seniority_group', 'count'],
            ['department', 'avg_age'],
            ['gender', 'avg_seniority'],
        ];

        foreach ($cases as [$dimension, $measure]) {
            $this->postJson('/api/v1/employees/reports/data', [
                'dimension' => $dimension,
                'measure' => $measure,
            ])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.dimension.key', $dimension)
                ->assertJsonStructure(['data' => ['data' => [['id', 'label', 'value']], 'measure', 'total']]);
        }

        $ageGroups = $this->postJson('/api/v1/employees/reports/data', [
            'dimension' => 'age_group',
            'measure' => 'count',
        ])->json('data.data');
        $this->assertEqualsCanonicalizing(['25-34', '45-54'], array_column($ageGroups, 'id'));

        $hireYears = $this->postJson('/api/v1/employees/reports/data', [
            'dimension' => 'hire_year',
            'measure' => 'count',
        ])->json('data.data');
        $this->assertContains((int) now()->subYears(2)->subDays(10)->format('Y'), array_map('intval', array_column($hireYears, 'id')));
    }

    public function test_employee_report_export_excel_with_date_dimension(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/employees/reports/export/excel', [
            'dimension' => 'seniority_group',
            'measure' => 'avg_age',
        ])->assertOk();

        $this->assertStringContainsString('Kıdem Grubu', $response->streamedContent());
    }

    public function test_employee_dashboard_widget_year_dimensions_and_age_measures(): void
    {
        Sanctum::actingAs($this->admin);

        foreach ([['hire_year', 'count'], ['birth_year', 'age'], ['department', 'tenure']] as [$dimension, $measure]) {
            $this->postJson('/api/v1/employees/dashboards/widget-data', [
                'type' => 'chart',
                'config' => ['dimension' => $dimension, 'measure' => $measure],
            ])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['data' => [['name', 'value']]]);
        }

        $birthYears = $this->postJson('/api/v1/employees/dashboards/widget-data', [
            'type' => 'chart',
            'config' => ['dimension' => 'birth_year', 'measure' => 'count'],
        ])->json('data');
        $this->assertCount(2, $birthYears);
    }

    public function test_document_widget_month_and_year_dimensions(): void
    {
        Document::create([
            'company_id' => $this->company->id,
            'name' => 'Sözleşme',
            'file_name' => 'sozlesme.pdf',
            'file_path' => 'documents/sozlesme.pdf',
            'file_size' => 2048,
            'file_type' => 'pdf',
            'approval_status' => 'approved',
            'uploaded_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $month = $this->postJson('/api/v1/documents/reports/widget-data', [
            'dimension' => 'month',
            'measure' => 'count',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['name', 'value']]])
            ->json('data');
        $this->assertSame(1.0, (float) $month[0]['value']);
        $this->assertStringEndsWith((string) now()->year, $month[0]['name']);

        $year = $this->postJson('/api/v1/documents/reports/widget-data', [
            'dimension' => 'year',
            'measure' => 'total_size',
        ])
            ->assertOk()
            ->assertJsonPath('data.0.name', (string) now()->year)
            ->json('data');
        $this->assertSame(2048.0, (float) $year[0]['value']);
    }

    public function test_document_kpi_this_month(): void
    {
        Document::create([
            'company_id' => $this->company->id,
            'name' => 'Yönetmelik',
            'file_name' => 'yonetmelik.pdf',
            'file_path' => 'documents/yonetmelik.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'approval_status' => 'approved',
            'uploaded_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $value = $this->postJson('/api/v1/documents/reports/kpi-data', ['kpi_type' => 'this_month'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['value', 'label']])
            ->json('data.value');
        $this->assertSame(1, (int) $value);
    }

    public function test_recruitment_trends_day_week_month_intervals(): void
    {
        $position = JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Muhasebe Uzmanı',
            'slug' => 'muhasebe-uzmani-adim01',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'published_at' => now(),
        ]);
        JobApplication::create([
            'company_id' => $this->company->id,
            'job_position_id' => $position->id,
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'email' => 'ayse.adim01@example.test',
            'status' => 'hired',
        ]);

        Sanctum::actingAs($this->admin);

        $expected = [
            'day' => now()->format('Y-m-d'),
            'week' => now()->format('o-W'),
            'month' => now()->format('Y-m'),
        ];

        foreach ($expected as $interval => $period) {
            $rows = $this->getJson('/api/v1/recruitment/reports/trends?'.http_build_query([
                'interval' => $interval,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ]))
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['data' => [['period', 'total', 'hired', 'rejected']]])
                ->assertJsonPath('data.0.period', $period)
                ->json('data');
            $this->assertSame(1, (int) $rows[0]['hired']);
        }
    }
}
