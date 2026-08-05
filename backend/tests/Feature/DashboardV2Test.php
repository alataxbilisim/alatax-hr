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
use App\Services\Reports\DashboardService;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D1d — Dashboard v2: batch run, kapsam, filtre, guard.
 */
class DashboardV2Test extends TestCase
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
            'employee_code' => 'D1',
            'status' => 'active',
            'department_id' => $this->deptA->id,
            'gross_salary' => 50000,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'D2',
            'status' => 'active',
            'department_id' => $this->deptB->id,
            'gross_salary' => 80000,
        ]);
    }

    public function test_batch_run_returns_all_widgets_and_isolates_errors(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $report = SavedReport::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'name' => 'Emp count',
            'dataset_key' => 'employees',
            'config' => [
                'dataset' => 'employees',
                'fields' => ['employee_code', 'status'],
                'aggregations' => [['fn' => 'count', 'field' => '*', 'alias' => 'adet']],
                'group_by' => ['status'],
            ],
            'is_shared' => true,
        ]);

        $create = $this->postJson('/api/v1/dashboards', [
            'name' => 'Ops Pano',
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'w1',
                        'type' => 'kpi',
                        'title' => 'Adet',
                        'config' => [
                            'dataset' => 'employees',
                            'fields' => ['id'],
                            'aggregations' => [['fn' => 'count', 'field' => '*', 'alias' => 'value']],
                        ],
                    ],
                    [
                        'id' => 'w2',
                        'type' => 'table',
                        'title' => 'Rapor',
                        'report_id' => $report->id,
                    ],
                    [
                        'id' => 'w3',
                        'type' => 'kpi',
                        'title' => 'Bozuk',
                        'config' => [
                            'dataset' => 'employees',
                            'fields' => ['id'],
                            'aggregations' => [['fn' => 'sum', 'field' => 'not_a_real_field', 'alias' => 'x']],
                        ],
                    ],
                ],
            ],
        ])->assertCreated()->json('data');

        $id = (int) $create['id'];
        $run = $this->postJson("/api/v1/dashboards/{$id}/run", [])->assertOk()->json('data');

        $this->assertCount(3, $run['widgets']);
        $byId = collect($run['widgets'])->keyBy('id');
        $this->assertTrue($byId['w1']['success']);
        $this->assertTrue($byId['w2']['success']);
        $this->assertFalse($byId['w3']['success']);
        $this->assertNotEmpty($byId['w3']['error']);
    }

    public function test_shared_dashboard_uses_viewer_scope_not_owner(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $dash = $this->postJson('/api/v1/dashboards', [
            'name' => 'Paylaşılan',
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'tbl',
                        'type' => 'table',
                        'title' => 'Personel',
                        'config' => [
                            'dataset' => 'employees',
                            'fields' => ['employee_code', 'department_id'],
                            'limit' => 50,
                        ],
                    ],
                ],
            ],
            'shares' => [],
        ])->assertCreated()->json('data');

        $mgr = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $mgr->id,
            'employee_code' => 'MGR-D',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        $role = Role::findOrCreate('dash_viewer', 'sanctum');
        $role->forceFill(['data_scope' => 'department'])->save();
        $role->givePermissionTo([
            'reports.dashboards.view',
            'reports.definitions.run',
            'employees.list.view',
        ]);
        $mgr->assignRole($role);

        // share with user
        $dashboard = Dashboard::findOrFail($dash['id']);
        $dashboard->shares()->create([
            'company_id' => $this->company->id,
            'user_id' => $mgr->id,
            'role_id' => null,
            'level' => 'viewer',
        ]);

        Sanctum::actingAs($mgr->fresh());
        $run = $this->postJson('/api/v1/dashboards/'.$dash['id'].'/run')->assertOk()->json('data');
        $this->assertSame('department', $run['meta']['data_scope']);
        $this->assertTrue($run['widgets'][0]['success']);
        $codes = collect($run['widgets'][0]['data']['rows'])->pluck('employee_code');
        $this->assertTrue($codes->contains('D1') || $codes->contains('MGR-D'));
        $this->assertFalse($codes->contains('D2')); // dept B
    }

    public function test_salary_kpi_hidden_without_permission(): void
    {
        $viewer = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('dash_nosal', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo([
            'reports.dashboards.view',
            'reports.dashboards.create',
            'employees.list.view',
        ]);
        $viewer->assignRole($role);

        Sanctum::actingAs($viewer->fresh());
        $dash = $this->postJson('/api/v1/dashboards', [
            'name' => 'Maaş KPI',
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'sal',
                        'type' => 'kpi',
                        'title' => 'Brüt',
                        'config' => [
                            'dataset' => 'employees',
                            'fields' => ['gross_salary'],
                            'aggregations' => [
                                ['fn' => 'sum', 'field' => 'gross_salary', 'alias' => 'value'],
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertCreated()->json('data');

        $run = $this->postJson('/api/v1/dashboards/'.$dash['id'].'/run')->assertOk()->json('data');
        $this->assertFalse($run['widgets'][0]['success']);
    }

    public function test_global_filter_skipped_when_field_absent(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $dash = $this->postJson('/api/v1/dashboards', [
            'name' => 'Filtre',
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'w',
                        'type' => 'table',
                        'title' => 'Personel',
                        'config' => [
                            'dataset' => 'employees',
                            'fields' => ['employee_code'],
                        ],
                    ],
                ],
            ],
        ])->assertCreated()->json('data');

        $run = $this->postJson('/api/v1/dashboards/'.$dash['id'].'/run', [
            'values' => [
                'nonexistent_field_xyz' => 'x',
                'status' => 'active',
            ],
        ])->assertOk()->json('data');

        $meta = $run['widgets'][0]['meta'];
        $this->assertContains('nonexistent_field_xyz', $meta['filter_skipped_keys']);
        $this->assertTrue($meta['filter_applied']);
    }

    public function test_widget_limit_guard(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $widgets = [];
        for ($i = 0; $i < 21; $i++) {
            $widgets[] = [
                'id' => 'w'.$i,
                'type' => 'text',
                'title' => 'T'.$i,
                'content' => 'x',
            ];
        }
        $this->postJson('/api/v1/dashboards', [
            'name' => 'Too many',
            'layout' => ['widgets' => $widgets],
        ])->assertStatus(422);
    }

    public function test_unauthenticated_401(): void
    {
        $this->getJson('/api/v1/dashboards')->assertUnauthorized();
    }

    public function test_unauthorized_403(): void
    {
        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/dashboards')->assertForbidden();
    }

    public function test_constants(): void
    {
        $this->assertSame(20, DashboardService::MAX_WIDGETS);
        $this->assertSame(30, DashboardService::MIN_REFRESH_SEC);
        $this->assertSame(8, DashboardService::WIDGET_TIMEOUT_SEC);
        $this->assertSame(45, DashboardService::BATCH_TIMEOUT_SEC);
    }

    /** Tur7 — layout kaydı 200; yetkisiz → 403 (422 yetki mesajı değil). */
    public function test_owner_can_save_layout_and_stranger_gets_403(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $dash = $this->postJson('/api/v1/dashboards', [
            'name' => 'Tur7 Pano',
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'w1',
                        'type' => 'text',
                        'title' => 'Not',
                        'content' => 'hello',
                        'layout' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
                    ],
                ],
            ],
        ])->assertCreated()->json('data');

        $this->putJson('/api/v1/dashboards/'.$dash['id'], [
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'w1',
                        'type' => 'text',
                        'title' => 'Not',
                        'content' => 'hello',
                        'layout' => ['x' => 2, 'y' => 1, 'w' => 6, 'h' => 4],
                    ],
                ],
            ],
        ])->assertOk();

        $fresh = Dashboard::query()->find($dash['id']);
        $this->assertSame(2, $fresh->widgets()[0]['layout']['x'] ?? null);

        $other = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $other->givePermissionTo(['reports.dashboards.view']);
        Sanctum::actingAs($other->fresh());

        $this->putJson('/api/v1/dashboards/'.$dash['id'], [
            'layout' => ['widgets' => []],
        ])->assertForbidden();
    }

    /**
     * QA-3 regresyon: sistem panosu widget'ları company_id NULL raporlara report_id ile bağlanır.
     * BelongsToCompany + where(company_id) bu raporları gizleyince "Widget raporu bulunamadı" oluşuyordu.
     */
    public function test_system_dashboard_widgets_resolve_system_report_ids(): void
    {
        $sysReport = SavedReport::withoutGlobalScopes()->create([
            'company_id' => null,
            'user_id' => null,
            'name' => 'Sistem Emp Count',
            'dataset_key' => 'employees',
            'config' => [
                'dataset' => 'employees',
                'fields' => ['status'],
                'aggregations' => [['fn' => 'count', 'field' => '*', 'alias' => 'adet']],
                'group_by' => ['status'],
            ],
            'is_system' => true,
            'system_key' => 'qa3.system_emp_count',
            'is_shared' => false,
        ]);

        $dash = Dashboard::withoutGlobalScopes()->create([
            'company_id' => null,
            'owner_id' => null,
            'created_by' => null,
            'name' => 'QA3 Sistem Pano',
            'is_system' => true,
            'system_key' => 'qa3.system_dash',
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'sys-kpi',
                        'type' => 'kpi',
                        'title' => 'Sistem KPI',
                        'report_id' => $sysReport->id,
                        'layout' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
                    ],
                ],
            ],
            'global_filters' => ['fields' => []],
        ]);

        Sanctum::actingAs($this->admin->fresh());
        $run = $this->postJson('/api/v1/dashboards/'.$dash->id.'/run', [])
            ->assertOk()
            ->json('data');

        $widget = collect($run['widgets'])->firstWhere('id', 'sys-kpi');
        $this->assertNotNull($widget);
        $this->assertTrue($widget['success'], $widget['error'] ?? 'widget failed');
        $this->assertNull($widget['error']);
    }
}
