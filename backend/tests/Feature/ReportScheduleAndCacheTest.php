<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ReportAccessLog;
use App\Models\ReportSchedule;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\ReportAttachmentGuard;
use App\Services\Reports\ReportScheduleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D1f — Cache izolasyonu, zamanlama kapsamı, ek guard, max alıcı, 3 hata pasif.
 */
class ReportScheduleAndCacheTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private Department $deptA;

    private Department $deptB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Cache::flush();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
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
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();

        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'C1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'gross_salary' => 50000,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'C2',
            'status' => 'active',
            'department_id' => $this->deptB->id,
            'gross_salary' => 80000,
        ]);
    }

    public function test_cache_isolation_by_data_scope_and_field_permission(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Cache izolasyon',
            'dataset_key' => 'employees',
            'config' => [
                'dataset' => 'employees',
                'fields' => ['employee_code', 'gross_salary'],
            ],
            'cache_ttl_seconds' => 300,
        ])->assertCreated()->json('data');

        $companyUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $companyRole = Role::findOrCreate('cache_company', 'sanctum');
        $companyRole->forceFill(['data_scope' => 'company'])->save();
        $companyRole->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
            'employees.list.view',
            'employees.salary.view',
        ]);
        $companyUser->assignRole($companyRole);

        $deptUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $deptUser->id,
            'employee_code' => 'DU1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        $deptRole = Role::findOrCreate('cache_dept', 'sanctum');
        $deptRole->forceFill(['data_scope' => 'department'])->save();
        $deptRole->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
            'employees.list.view',
            'employees.salary.view',
        ]);
        $deptUser->assignRole($deptRole);

        $this->putJson('/api/v1/reports/'.$report['id'], [
            'shares' => [
                ['user_id' => $companyUser->id, 'level' => 'viewer'],
                ['user_id' => $deptUser->id, 'level' => 'viewer'],
            ],
        ])->assertOk();

        Sanctum::actingAs($companyUser->fresh()->load(['roles', 'employee']));
        $runA = $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk()->json('data');
        $countA = count($runA['rows']);
        $this->assertGreaterThanOrEqual(2, $countA);
        $this->assertFalse($runA['meta']['cache_hit'] ?? true);

        // İkinci çalıştırma cache hit
        $runA2 = $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk()->json('data');
        $this->assertTrue($runA2['meta']['cache_hit'] ?? false);

        Sanctum::actingAs($deptUser->fresh()->load(['roles', 'employee']));
        $runB = $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk()->json('data');
        $countB = count($runB['rows']);
        $this->assertLessThan($countA, $countB);
        $this->assertFalse($runB['meta']['cache_hit'] ?? true);

        // Maaş yetkisi olmayan kullanıcı farklı cache (hidden field)
        $noSalary = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $nsRole = Role::findOrCreate('cache_nosal', 'sanctum');
        $nsRole->forceFill(['data_scope' => 'company'])->save();
        $nsRole->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
            'employees.list.view',
        ]);
        $noSalary->assignRole($nsRole);
        Sanctum::actingAs($this->admin->fresh());
        $this->putJson('/api/v1/reports/'.$report['id'], [
            'shares' => [
                ['user_id' => $companyUser->id, 'level' => 'viewer'],
                ['user_id' => $deptUser->id, 'level' => 'viewer'],
                ['user_id' => $noSalary->id, 'level' => 'viewer'],
            ],
        ])->assertOk();

        Sanctum::actingAs($noSalary->fresh()->load(['roles', 'employee']));
        $runC = $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk()->json('data');
        $this->assertFalse($runC['meta']['cache_hit'] ?? true);
        $hidden = collect($runC['meta']['hidden_fields'] ?? [])->pluck('key');
        $this->assertTrue($hidden->contains('gross_salary'));
    }

    public function test_cache_isolation_by_report_scope_group(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'G2 cache scope',
            'dataset_key' => 'employees',
            'config' => [
                'dataset' => 'employees',
                'fields' => ['employee_code'],
            ],
            'cache_ttl_seconds' => 300,
        ])->assertCreated()->json('data');

        $runCompany = $this->postJson('/api/v1/reports/'.$report['id'].'/run', [
            'scope' => 'company',
        ])->assertOk()->json('data');
        $this->assertFalse($runCompany['meta']['cache_hit'] ?? true);

        $runCompany2 = $this->postJson('/api/v1/reports/'.$report['id'].'/run', [
            'scope' => 'company',
        ])->assertOk()->json('data');
        $this->assertTrue($runCompany2['meta']['cache_hit'] ?? false);

        $runGroup = $this->postJson('/api/v1/reports/'.$report['id'].'/run', [
            'scope' => 'group',
        ])->assertOk()->json('data');
        $this->assertFalse($runGroup['meta']['cache_hit'] ?? true);
        $this->assertSame('group', $runGroup['meta']['report_scope'] ?? null);
    }

    public function test_schedule_per_recipient_scope_and_skip_no_access(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->admin->fresh());

        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Zamanlama kapsam',
            'dataset_key' => 'employees',
            'config' => ['dataset' => 'employees', 'fields' => ['employee_code']],
        ])->assertCreated()->json('data');

        $userA = User::factory()->create(['home_company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $userA->id,
            'employee_code' => 'SA1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        $roleA = Role::findOrCreate('sched_a', 'sanctum');
        $roleA->forceFill(['data_scope' => 'department'])->save();
        $roleA->givePermissionTo(['reports.definitions.view', 'reports.definitions.run', 'employees.list.view']);
        $userA->assignRole($roleA);

        $userB = User::factory()->create(['home_company_id' => $this->company->id, 'type' => UserType::User]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $userB->id,
            'employee_code' => 'SB1',
            'status' => 'active',
            'department_id' => $this->deptB->id,
        ]);
        $roleB = Role::findOrCreate('sched_b', 'sanctum');
        $roleB->forceFill(['data_scope' => 'department'])->save();
        $roleB->givePermissionTo(['reports.definitions.view', 'reports.definitions.run', 'employees.list.view']);
        $userB->assignRole($roleB);

        $noAccess = User::factory()->create(['home_company_id' => $this->company->id, 'type' => UserType::User]);
        $roleN = Role::findOrCreate('sched_n', 'sanctum');
        $roleN->givePermissionTo(['reports.definitions.view', 'reports.definitions.run', 'employees.list.view']);
        $noAccess->assignRole($roleN);

        $this->putJson('/api/v1/reports/'.$report['id'], [
            'shares' => [
                ['user_id' => $userA->id, 'level' => 'viewer'],
                ['user_id' => $userB->id, 'level' => 'viewer'],
            ],
        ])->assertOk();

        $schedule = $this->postJson('/api/v1/reports/schedules', [
            'name' => 'Günlük',
            'report_id' => $report['id'],
            'cadence' => 'daily',
            'hour' => 8,
            'minute' => 0,
            'format' => 'link',
            'recipients' => [
                ['user_id' => $userA->id],
                ['user_id' => $userB->id],
                ['user_id' => $noAccess->id],
            ],
        ])->assertCreated()->json('data');

        $result = $this->postJson('/api/v1/reports/schedules/'.$schedule['id'].'/run')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $result['delivered']);
        $this->assertSame(1, $result['skipped']);
        $skip = collect($result['logs'])->firstWhere('status', 'skipped_no_access');
        $this->assertNotNull($skip);
        $this->assertSame($noAccess->id, $skip['user_id']);

        $rowsA = collect($result['logs'])->firstWhere('user_id', $userA->id)['rows'] ?? null;
        $rowsB = collect($result['logs'])->firstWhere('user_id', $userB->id)['rows'] ?? null;
        $this->assertNotNull($rowsA);
        $this->assertNotNull($rowsB);
        // Her departmanda en az 1 employee (+ kendi kaydı olabilir)
        $this->assertGreaterThan(0, $rowsA);
        $this->assertGreaterThan(0, $rowsB);

        $this->assertTrue(
            ReportAccessLog::query()->where('action', 'scheduled')->where('report_id', $report['id'])->exists()
        );
    }

    public function test_schedule_rejects_over_50_recipients(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Max alıcı',
            'dataset_key' => 'employees',
            'config' => ['dataset' => 'employees', 'fields' => ['employee_code']],
        ])->assertCreated()->json('data');

        $recipients = [];
        for ($i = 0; $i < 51; $i++) {
            $u = User::factory()->create(['home_company_id' => $this->company->id, 'type' => UserType::User]);
            $recipients[] = ['user_id' => $u->id];
        }

        $this->postJson('/api/v1/reports/schedules', [
            'name' => 'Çok alıcı',
            'report_id' => $report['id'],
            'cadence' => 'daily',
            'format' => 'link',
            'recipients' => $recipients,
        ])->assertStatus(422);
    }

    public function test_three_failures_deactivate_schedule(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Fail schedule',
            'dataset_key' => 'employees',
            'config' => ['dataset' => 'employees', 'fields' => ['employee_code']],
        ])->assertCreated()->json('data');

        $schedule = ReportSchedule::create([
            'company_id' => $this->company->id,
            'report_id' => $report['id'],
            'owner_id' => $this->admin->id,
            'name' => 'Failing',
            'cadence' => 'daily',
            'hour' => 8,
            'minute' => 0,
            'timezone' => 'Europe/Istanbul',
            'format' => 'link',
            'recipients' => [['user_id' => $this->admin->id]],
            'active' => true,
            'failure_count' => 0,
            'next_run_at' => now()->subMinute(),
        ]);

        // Rapor dataset'ini geçersizleştir — çalıştırma fail eder (cascade silme zamanlamayı da siler)
        \Illuminate\Support\Facades\DB::table('saved_reports')
            ->where('id', $report['id'])
            ->update(['dataset_key' => '__invalid_dataset__']);

        $svc = app(ReportScheduleService::class);
        $id = (int) $schedule->id;
        for ($i = 0; $i < 3; $i++) {
            try {
                $svc->runSchedule(ReportSchedule::query()->findOrFail($id));
            } catch (\Throwable) {
                // expected
            }
        }
        $schedule = ReportSchedule::query()->findOrFail($id);
        $this->assertFalse($schedule->active);
        $this->assertGreaterThanOrEqual(3, $schedule->failure_count);
    }

    public function test_special_field_blocks_attachment_format(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Special',
            'dataset_key' => 'leave_requests',
            'config' => [
                'dataset' => 'leave_requests',
                'fields' => ['status', 'reason'],
            ],
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/reports/schedules', [
            'name' => 'Ek engel',
            'report_id' => $report['id'],
            'cadence' => 'daily',
            'format' => 'excel',
            'recipients' => [['user_id' => $this->admin->id]],
        ])->assertStatus(422);

        $model = SavedReport::find($report['id']);
        $this->assertTrue(app(ReportAttachmentGuard::class)->blocksAttachment($model));
        $this->assertSame('link', app(ReportAttachmentGuard::class)->effectiveFormat($model, 'pdf'));
    }

    public function test_bypass_cache_flag(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Bypass',
            'dataset_key' => 'employees',
            'config' => ['dataset' => 'employees', 'fields' => ['employee_code']],
            'cache_ttl_seconds' => 300,
        ])->assertCreated()->json('data');

        $r1 = $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk()->json('data');
        $this->assertFalse($r1['meta']['cache_hit'] ?? true);
        $r2 = $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk()->json('data');
        $this->assertTrue($r2['meta']['cache_hit'] ?? false);
        $r3 = $this->postJson('/api/v1/reports/'.$report['id'].'/run', [
            'bypass_cache' => true,
        ])->assertOk()->json('data');
        $this->assertFalse($r3['meta']['cache_hit'] ?? true);
        $this->assertArrayHasKey('computed_at', $r3['meta']);
    }
}
