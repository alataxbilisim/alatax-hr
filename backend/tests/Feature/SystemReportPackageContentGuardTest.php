<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Dashboard;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\DatasetRegistry;
use App\Services\Reports\ReportDefinitionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemReportPackageSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-3 yapısal guard: seeder sistem rapor/panoları CI'da çalıştırılabilir olmalı.
 * Yeni paket tanımı bozulursa test KIRMIZI — QA turuna bırakılmaz.
 */
class SystemReportPackageContentGuardTest extends TestCase
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
        $dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Guard Dept',
            'code' => 'GD',
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
            'user_id' => $this->admin->id,
            'employee_code' => 'G-001',
            'status' => 'active',
            'department_id' => $dept->id,
        ]);

        $this->seed(SystemReportPackageSeeder::class);
    }

    public function test_every_system_report_dataset_and_fields_exist_in_registry(): void
    {
        $registry = app(DatasetRegistry::class);
        $reports = SavedReport::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->get();

        $this->assertGreaterThanOrEqual(30, $reports->count());

        foreach ($reports as $report) {
            $this->assertTrue(
                $registry->has($report->dataset_key),
                "Sistem raporu [{$report->system_key}] dataset_key registry'de yok: {$report->dataset_key}"
            );

            $dataset = $registry->get($report->dataset_key);
            $known = collect($dataset->fieldsForCompany((int) $this->company->id))
                ->map(fn ($f) => $f->key)
                ->all();

            $config = is_array($report->config) ? $report->config : [];
            foreach (array_merge(
                is_array($config['fields'] ?? null) ? $config['fields'] : [],
                is_array($config['group_by'] ?? null) ? $config['group_by'] : [],
            ) as $field) {
                if (! is_string($field) || $field === '') {
                    continue;
                }
                $this->assertContains(
                    $field,
                    $known,
                    "Sistem raporu [{$report->system_key}] bilinmeyen alan: {$field}"
                );
            }

            foreach (is_array($config['aggregations'] ?? null) ? $config['aggregations'] : [] as $agg) {
                if (! is_array($agg)) {
                    continue;
                }
                $field = $agg['field'] ?? null;
                if (! is_string($field) || $field === '' || $field === '*' || $field === 'id') {
                    continue;
                }
                $this->assertContains(
                    $field,
                    $known,
                    "Sistem raporu [{$report->system_key}] bilinmeyen ölçü alanı: {$field}"
                );
            }
        }
    }

    public function test_every_system_report_runs_without_error(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $service = app(ReportDefinitionService::class);

        $reports = SavedReport::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->get();

        $failures = [];
        foreach ($reports as $report) {
            try {
                $service->run($report, $this->admin->fresh(), (int) $this->company->id, [
                    'limit' => 5,
                    '__bypass_cache' => true,
                ]);
            } catch (\Throwable $e) {
                $failures[] = "{$report->system_key}: {$e->getMessage()}";
            }
        }

        $this->assertSame([], $failures, "Sistem raporları hata verdi:\n".implode("\n", $failures));
    }

    public function test_every_system_dashboard_batch_run_widgets_succeed(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $dashboards = Dashboard::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->get();

        $this->assertGreaterThanOrEqual(9, $dashboards->count());

        $emptyWidgets = [];
        $failures = [];

        foreach ($dashboards as $dashboard) {
            $run = $this->postJson('/api/v1/dashboards/'.$dashboard->id.'/run', [])
                ->assertOk()
                ->json('data');

            $widgets = is_array($run['widgets'] ?? null) ? $run['widgets'] : [];
            $this->assertNotEmpty($widgets, "Pano [{$dashboard->system_key}] widget yok");

            foreach ($widgets as $w) {
                $wid = (string) ($w['id'] ?? '?');
                if (! ($w['success'] ?? false)) {
                    $failures[] = "{$dashboard->system_key}/{$wid}: ".($w['error'] ?? 'unknown');
                    continue;
                }
                $data = $w['data'] ?? null;
                $isEmpty = $data === null
                    || $data === []
                    || (is_array($data) && ($data['rows'] ?? null) === []);
                if ($isEmpty) {
                    $emptyWidgets[] = "{$dashboard->system_key}/{$wid}";
                }
            }
        }

        $this->assertSame([], $failures, "Sistem pano widget hataları:\n".implode("\n", $failures));

        // Boş sonuç kabul; uyarı olarak logla (test kırılmaz)
        if ($emptyWidgets !== []) {
            fwrite(STDERR, "[SystemReportPackageContentGuard] boş widget (uyarı): ".implode(', ', $emptyWidgets)."\n");
        }
    }
}
