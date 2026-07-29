<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Dashboard;
use App\Models\Department;
use App\Models\Employee;
use App\Models\RoleDefaultDashboard;
use App\Models\SavedReport;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemReportPackageSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D1g — hazır rapor paketi, yeni dataset'ler, kopyala, module_key, analytics DataScope.
 */
class ReportPackageD1gTest extends TestCase
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

    public function test_system_package_seed_is_idempotent_and_preserves_company_copies(): void
    {
        $this->seed(SystemReportPackageSeeder::class);
        $reportCount = SavedReport::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->count();
        $dashCount = Dashboard::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->count();
        $this->assertGreaterThanOrEqual(30, $reportCount);
        $this->assertGreaterThanOrEqual(9, $dashCount);
        $this->assertDatabaseHas('role_default_dashboards', [
            'role_key' => 'company_admin',
            'dashboard_system_key' => 'hr-analytics.overview',
        ]);

        $system = SavedReport::withoutGlobalScopes()
            ->where('system_key', 'leaves.by_status')
            ->whereNull('company_id')
            ->firstOrFail();
        $this->assertSame('İzin durum dağılımı', $system->name);

        Sanctum::actingAs($this->admin->fresh());
        $clone = $this->postJson('/api/v1/reports/'.$system->id.'/clone', [
            'name' => 'Firma özel izin durumu',
        ])->assertCreated()->json('data');

        $this->assertSame($this->company->id, (int) $clone['company_id']);
        $this->assertNull($clone['system_key'] ?? null);
        $this->assertFalse((bool) ($clone['is_system'] ?? false));

        SavedReport::withoutGlobalScopes()->whereKey($clone['id'])->update([
            'name' => 'Özelleştirilmiş kopya ASLA ezilmesin',
            'config' => array_merge($system->config ?? [], ['limit' => 7]),
        ]);

        $this->seed(SystemReportPackageSeeder::class);
        $this->seed(SystemReportPackageSeeder::class);

        $this->assertSame(
            $reportCount,
            SavedReport::withoutGlobalScopes()->whereNull('company_id')->where('is_system', true)->count()
        );
        $this->assertSame(
            $dashCount,
            Dashboard::withoutGlobalScopes()->whereNull('company_id')->where('is_system', true)->count()
        );
        $this->assertSame(4, RoleDefaultDashboard::query()->count());

        $copy = SavedReport::withoutGlobalScopes()->findOrFail($clone['id']);
        $this->assertSame('Özelleştirilmiş kopya ASLA ezilmesin', $copy->name);
        $this->assertSame(7, (int) ($copy->config['limit'] ?? 0));

        $systemFresh = SavedReport::withoutGlobalScopes()->findOrFail($system->id);
        $this->assertSame('İzin durum dağılımı', $systemFresh->name);
        $this->assertTrue($systemFresh->is_system);
        $this->assertNull($systemFresh->company_id);
    }

    public function test_new_datasets_catalog_and_preview_smoke_with_scope(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $keys = collect($this->getJson('/api/v1/reports/datasets')->assertOk()->json('data'))->pluck('key');
        foreach ([
            'attendance_records',
            'assets',
            'training_participants',
            'employee_documents',
            'payslips',
            'survey_responses',
        ] as $k) {
            $this->assertTrue($keys->contains($k), "missing dataset {$k}");
        }

        AttendanceRecord::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'date' => '2026-07-01',
            'status' => 'present',
        ]);
        AttendanceRecord::factory()->create([
            'company_id' => $this->otherCompany->id,
            'user_id' => User::factory()->create(['company_id' => $this->otherCompany->id])->id,
            'date' => '2026-07-01',
            'status' => 'present',
        ]);

        $preview = $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'attendance_records',
            'fields' => ['user_id', 'date', 'status'],
            'limit' => 50,
        ])->assertOk()->json('data');
        $this->assertSame('attendance_records', $preview['meta']['dataset']);
        $this->assertCount(1, $preview['rows']);

        foreach (['assets', 'training_participants', 'employee_documents', 'payslips'] as $ds) {
            $this->postJson('/api/v1/reports/preview', [
                'dataset' => $ds,
                'fields' => ['id'],
                'limit' => 5,
            ])->assertOk()->assertJsonPath('data.meta.dataset', $ds);
        }

        // payslips hassas alan — izinsiz kullanıcıda gizlenir
        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('d1g_no_salary', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo(['reports.definitions.run', 'employees.list.view']);
        $viewer->assignRole($role);
        Sanctum::actingAs($viewer->fresh());
        $pay = $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'payslips',
            'fields' => ['id', 'gross_salary', 'year'],
            'limit' => 5,
        ])->assertOk()->json('data');
        $this->assertNotContains('gross_salary', $pay['meta']['fields']);
    }

    public function test_module_key_filter_and_system_report_readonly_clone(): void
    {
        $this->seed(SystemReportPackageSeeder::class);
        Sanctum::actingAs($this->admin->fresh());

        $list = $this->getJson('/api/v1/reports?module_key=timesheet&per_page=100')
            ->assertOk()
            ->json('data');
        $items = is_array($list['data'] ?? null) ? $list['data'] : $list;
        $this->assertNotEmpty($items);
        foreach ($items as $row) {
            $this->assertSame('timesheet', $row['module_key'] ?? null);
        }

        $dashList = $this->getJson('/api/v1/dashboards?module_key=leave-management&per_page=50')
            ->assertOk()
            ->json('data');
        $dashItems = is_array($dashList['data'] ?? null) ? $dashList['data'] : $dashList;
        $this->assertNotEmpty($dashItems);
        foreach ($dashItems as $row) {
            $this->assertSame('leave-management', $row['module_key'] ?? null);
        }

        $byKey = $this->getJson('/api/v1/dashboards/by-key/hr-analytics.overview')
            ->assertOk()
            ->json('data');
        $this->assertSame('hr-analytics.overview', $byKey['system_key']);
        $this->assertTrue((bool) $byKey['is_system']);

        $sysReport = SavedReport::withoutGlobalScopes()
            ->where('system_key', 'timesheet.by_status')
            ->firstOrFail();

        $this->putJson('/api/v1/reports/'.$sysReport->id, [
            'name' => 'Hack',
        ])->assertForbidden();

        $this->postJson('/api/v1/reports/'.$sysReport->id.'/clone')
            ->assertCreated()
            ->assertJsonPath('data.is_system', false);
    }

    public function test_analytics_system_report_applies_department_datascope(): void
    {
        $this->seed(SystemReportPackageSeeder::class);

        $mgrUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $mgrUser->id,
            'employee_code' => 'MGR-D1G',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'IN-A-D1G',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'IN-B-D1G',
            'status' => 'active',
            'department_id' => $this->deptB->id,
        ]);

        $role = Role::findOrCreate('d1g_dept_analytics', 'sanctum');
        $role->forceFill(['data_scope' => 'department'])->save();
        $role->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
            'reports.dashboards.view',
            'employees.list.view',
        ]);
        $mgrUser->assignRole($role);

        $report = SavedReport::withoutGlobalScopes()
            ->where('system_key', 'analytics.workforce_dept')
            ->whereNull('company_id')
            ->firstOrFail();

        Sanctum::actingAs($mgrUser->fresh());
        $result = $this->postJson('/api/v1/reports/'.$report->id.'/run', ['limit' => 100])
            ->assertOk()
            ->json('data');

        $deptIds = collect($result['rows'])->pluck('department_id')->map(fn ($v) => (int) $v);
        $this->assertTrue($deptIds->contains($this->deptA->id));
        $this->assertFalse($deptIds->contains($this->deptB->id));

        // Detay satırları da kapsamlı
        $detail = $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'department_id'],
            'limit' => 100,
        ])->assertOk()->json('data');
        $codes = collect($detail['rows'])->pluck('employee_code');
        $this->assertTrue($codes->contains('IN-A-D1G'));
        $this->assertFalse($codes->contains('IN-B-D1G'));
    }

    public function test_survey_anonymous_drill_still_forbidden(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/drill', [
            'dataset' => 'survey_responses',
            'mode' => 'details',
            'fields' => ['id', 'answer_numeric'],
        ])->assertForbidden();
    }
}
