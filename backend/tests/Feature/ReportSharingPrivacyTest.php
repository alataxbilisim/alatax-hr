<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ReportAccessLog;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\ReportPrivacySettings;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D1e — Paylaşım v2, şeffaf gizleme, erişim logu, min hücre guard.
 */
class ReportSharingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private Department $deptA;

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
        $this->admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();
    }

    public function test_viewer_cannot_edit_editor_cannot_delete_owner_can_transfer(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Paylaşım',
            'dataset_key' => 'employees',
            'config' => ['dataset' => 'employees', 'fields' => ['employee_code']],
        ])->assertCreated()->json('data');

        $viewer = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('rep_viewer', 'sanctum');
        $role->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.edit',
            'reports.definitions.delete',
            'reports.definitions.run',
            'employees.list.view',
        ]);
        $viewer->assignRole($role);

        $editor = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $erole = Role::findOrCreate('rep_editor', 'sanctum');
        $erole->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.edit',
            'reports.definitions.delete',
            'reports.definitions.run',
            'employees.list.view',
        ]);
        $editor->assignRole($erole);

        $this->putJson('/api/v1/reports/'.$report['id'], [
            'shares' => [
                ['user_id' => $viewer->id, 'level' => 'viewer'],
                ['user_id' => $editor->id, 'level' => 'editor'],
            ],
        ])->assertOk();

        Sanctum::actingAs($viewer->fresh());
        $this->putJson('/api/v1/reports/'.$report['id'], ['name' => 'Hacker'])->assertForbidden();

        Sanctum::actingAs($editor->fresh());
        $this->putJson('/api/v1/reports/'.$report['id'], ['name' => 'Editor OK'])->assertOk();
        $this->deleteJson('/api/v1/reports/'.$report['id'])->assertForbidden();

        $newOwner = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/'.$report['id'].'/transfer', [
            'user_id' => $newOwner->id,
        ])->assertOk();
        $this->assertSame($newOwner->id, (int) SavedReport::find($report['id'])->user_id);
    }

    public function test_department_share_visible_and_tenant_isolation(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Dept share',
            'dataset_key' => 'employees',
            'config' => ['dataset' => 'employees', 'fields' => ['employee_code']],
            'shares' => [
                ['department_id' => $this->deptA->id, 'level' => 'viewer'],
            ],
        ])->assertCreated()->json('data');

        $inDept = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $inDept->id,
            'employee_code' => 'IN-D',
            'status' => 'active',
            'department_id' => $this->deptA->id,
        ]);
        $role = Role::findOrCreate('dept_view', 'sanctum');
        $role->givePermissionTo(['reports.definitions.view', 'employees.list.view']);
        $inDept->assignRole($role);

        Sanctum::actingAs($inDept->fresh());
        $this->getJson('/api/v1/reports/'.$report['id'])->assertOk();

        $otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
        $outsider = User::factory()->create([
            'home_company_id' => $otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($outsider);
        Sanctum::actingAs($outsider->fresh());
        $this->getJson('/api/v1/reports/'.$report['id'])->assertNotFound();
    }

    public function test_hidden_fields_meta_on_run_without_salary_permission(): void
    {
        $viewer = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('nosal2', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
            'reports.definitions.create',
            'employees.list.view',
        ]);
        $viewer->assignRole($role);

        Sanctum::actingAs($viewer->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Maaş gizli',
            'dataset_key' => 'employees',
            'config' => [
                'dataset' => 'employees',
                'fields' => ['employee_code', 'gross_salary', 'net_salary'],
            ],
        ])->assertCreated()->json('data');

        $run = $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk()->json('data');
        $this->assertNotEmpty($run['meta']['hidden_fields']);
        $keys = collect($run['meta']['hidden_fields'])->pluck('key');
        $this->assertTrue($keys->contains('gross_salary'));
        $this->assertFalse(collect($run['meta']['fields'])->contains('gross_salary'));

        $export = $this->postJson('/api/v1/reports/export', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'gross_salary'],
        ])->assertOk()->json('data');
        $this->assertNotEmpty($export['meta']['export_note'] ?? null);
        $this->assertNotEmpty($export['meta']['hidden_fields']);
    }

    public function test_access_log_created_and_append_only_no_raw_filter_values(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $report = $this->postJson('/api/v1/reports', [
            'name' => 'Log',
            'dataset_key' => 'employees',
            'config' => [
                'dataset' => 'employees',
                'fields' => ['employee_code'],
                'filters' => [['field' => 'status', 'op' => 'eq', 'value' => 'active']],
            ],
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/reports/'.$report['id'].'/run')->assertOk();
        // afterResponse — flush
        $this->app->terminate();

        $log = ReportAccessLog::query()
            ->where('report_id', $report['id'])
            ->where('action', 'run')
            ->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->filters_hash);
        $this->assertContains('status', $log->filter_field_keys ?? []);
        $encoded = json_encode($log->getAttributes());
        $this->assertStringNotContainsString('active', $encoded ?? '');

        $this->expectException(LogicException::class);
        $log->update(['row_count' => 999]);
    }

    public function test_min_cell_masks_small_sick_leave_groups_and_threshold_setting(): void
    {
        $sick = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Hastalık',
            'code' => 'SICK',
            'system_code' => 'sick',
            'is_active' => true,
            'is_paid' => true,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $u = User::factory()->create([
                'home_company_id' => $this->company->id,
                'type' => UserType::User,
            ]);
            LeaveRequest::create([
                'company_id' => $this->company->id,
                'user_id' => $u->id,
                'leave_type_id' => $sick->id,
                'status' => 'approved',
                'start_date' => '2026-01-0'.$i,
                'end_date' => '2026-01-0'.$i,
                'total_days' => 1,
                'reason' => 'grip',
            ]);
        }

        Sanctum::actingAs($this->admin->fresh());
        $this->company->setSetting('report_privacy.min_cell_enabled', true);
        $this->company->setSetting('report_privacy.min_cell_threshold', 5);
        $this->company->save();

        $pivot = $this->postJson('/api/v1/reports/pivot', [
            'dataset' => 'leave_requests',
            'rows' => [['field' => 'leave_type_system_code']],
            'measures' => [['fn' => 'count', 'field' => '*', 'alias' => 'adet']],
        ])->assertOk()->json('data');

        $cell = collect($pivot['cells'])->firstWhere('measure', 'adet');
        $this->assertSame(ReportPrivacySettings::MASKED_VALUE, $cell['value']);

        $this->company->setSetting('report_privacy.min_cell_threshold', 1);
        $this->company->save();

        $pivot2 = $this->postJson('/api/v1/reports/pivot', [
            'dataset' => 'leave_requests',
            'rows' => [['field' => 'leave_type_system_code']],
            'measures' => [['fn' => 'count', 'field' => '*', 'alias' => 'adet']],
        ])->assertOk()->json('data');
        $cell2 = collect($pivot2['cells'])->firstWhere('measure', 'adet');
        $this->assertNotSame(ReportPrivacySettings::MASKED_VALUE, $cell2['value']);
        $this->assertEquals(3, (int) $cell2['value']);
    }

    public function test_survey_detail_drill_forbidden(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/reports/drill', [
            'dataset' => 'survey_responses',
            'mode' => 'details',
            'fields' => ['id', 'answer_numeric'],
        ])->assertForbidden();
    }

    public function test_access_log_cannot_be_deleted(): void
    {
        $log = ReportAccessLog::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'action' => 'preview',
            'dataset_key' => 'employees',
            'row_count' => 0,
            'contains_sensitive' => false,
            'created_at' => now(),
        ]);
        $this->expectException(LogicException::class);
        $log->delete();
    }
}
