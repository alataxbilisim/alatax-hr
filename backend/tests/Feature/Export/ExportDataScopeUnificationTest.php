<?php

namespace Tests\Feature\Export;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Export DataScope birliği — index toplamı = export satır sayısı; kapsam dışı yok.
 */
class ExportDataScopeUnificationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Department $deptA;

    private Department $deptB;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'employees.list.view',
            'employees.list.export',
            'employees.reports.view',
            'employees.reports.export',
            'management.users.view',
            'management.users.export',
            'management.audit_logs.view',
            'management.audit_logs.export',
        ] as $perm) {
            Permission::findOrCreate($perm, 'sanctum');
        }

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->deptA = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept A Export',
            'code' => 'DEA',
            'is_active' => true,
        ]);
        $this->deptB = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept B Export',
            'code' => 'DEB',
            'is_active' => true,
        ]);

        $this->actor = $this->makeDeptScopedActor();
    }

    private function makeDeptScopedActor(): User
    {
        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'name' => 'Export Actor',
            'email' => 'export.actor@test.local',
        ]);

        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
            'employee_code' => 'ACTOR-A',
            'full_name' => 'Export Actor',
        ]);

        $role = Role::findOrCreate('dept_exporter', 'sanctum');
        $role->forceFill([
            'data_scope' => 'department',
            'panel_access' => true,
        ])->save();
        $role->syncPermissions([
            'employees.list.view',
            'employees.list.export',
            'employees.reports.view',
            'employees.reports.export',
            'management.users.view',
            'management.users.export',
            'management.audit_logs.view',
            'management.audit_logs.export',
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function countCsvDataRows(string $body): int
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
        $lines = preg_split("/\r\n|\n|\r/", trim($body)) ?: [];
        $lines = array_values(array_filter($lines, static fn ($l) => trim((string) $l) !== ''));

        return max(0, count($lines) - 1);
    }

    public function test_employee_export_matches_index_scope_and_count(): void
    {
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
            'employee_code' => 'IN-SCOPE-E',
            'full_name' => 'In Scope Emp',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptB->id,
            'status' => 'active',
            'employee_code' => 'OUT-SCOPE-E',
            'full_name' => 'Out Scope Emp',
        ]);

        Sanctum::actingAs($this->actor);

        $indexTotal = (int) $this->getJson('/api/v1/employees?per_page=100')
            ->assertOk()
            ->json('meta.total');

        $export = $this->get('/api/v1/employees/export')->assertOk();
        $csv = $export->streamedContent();
        $exportRows = $this->countCsvDataRows($csv);

        $this->assertSame($indexTotal, $exportRows, 'Employee index total must equal export CSV rows');
        $this->assertGreaterThanOrEqual(2, $indexTotal);
        $this->assertStringContainsString('IN-SCOPE-E', $csv);
        $this->assertStringContainsString('ACTOR-A', $csv);
        $this->assertStringNotContainsString('OUT-SCOPE-E', $csv);
    }

    public function test_user_export_matches_index_scope_and_count(): void
    {
        $inUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'name' => 'In Scope User',
            'email' => 'in.user@test.local',
            'phone' => '5551110001',
        ]);
        $inRole = Role::findOrCreate('panel_peer', 'sanctum');
        $inRole->forceFill(['panel_access' => true, 'data_scope' => 'company'])->save();
        $inUser->assignRole($inRole);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $inUser->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
            'employee_code' => 'IN-U',
        ]);

        $outUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
            'name' => 'Out Scope User',
            'email' => 'out.user@test.local',
            'phone' => '5551110002',
        ]);
        $outUser->assignRole($inRole);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $outUser->id,
            'department_id' => $this->deptB->id,
            'status' => 'active',
            'employee_code' => 'OUT-U',
        ]);

        Sanctum::actingAs($this->actor->fresh());

        $indexTotal = (int) $this->getJson('/api/v1/users?per_page=100')
            ->assertOk()
            ->json('meta.total');

        $export = $this->get('/api/v1/users/export')->assertOk();
        $csv = $export->streamedContent();
        $exportRows = $this->countCsvDataRows($csv);

        $this->assertSame($indexTotal, $exportRows, 'User index total must equal export CSV rows');
        $this->assertStringContainsString('in.user@test.local', $csv);
        $this->assertStringNotContainsString('out.user@test.local', $csv);
    }

    public function test_activity_log_export_matches_index_scope_and_count(): void
    {
        $inUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'name' => 'Log In User',
            'email' => 'login@test.local',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $inUser->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
        ]);

        $outUser = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'name' => 'Log Out User',
            'email' => 'logout@test.local',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $outUser->id,
            'department_id' => $this->deptB->id,
            'status' => 'active',
        ]);

        ActivityLog::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $inUser->id,
            'user_name' => $inUser->name,
            'action' => 'update',
            'description' => 'IN-SCOPE-LOG-MARKER',
            'is_successful' => true,
        ]);
        ActivityLog::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $outUser->id,
            'user_name' => $outUser->name,
            'action' => 'update',
            'description' => 'OUT-SCOPE-LOG-MARKER',
            'is_successful' => true,
        ]);
        // Actor kendi logu (kendi user_id kapsamda)
        ActivityLog::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->actor->id,
            'user_name' => $this->actor->name,
            'action' => 'view',
            'description' => 'ACTOR-LOG-MARKER',
            'is_successful' => true,
        ]);

        Sanctum::actingAs($this->actor->fresh());

        $indexTotal = (int) $this->getJson('/api/v1/activity-logs?per_page=100')
            ->assertOk()
            ->json('meta.total');

        $export = $this->get('/api/v1/activity-logs/export')->assertOk();
        $csv = $export->streamedContent();
        $exportRows = $this->countCsvDataRows($csv);

        $this->assertSame($indexTotal, $exportRows, 'Activity log index total must equal export CSV rows');
        $this->assertStringContainsString('IN-SCOPE-LOG-MARKER', $csv);
        $this->assertStringContainsString('ACTOR-LOG-MARKER', $csv);
        $this->assertStringNotContainsString('OUT-SCOPE-LOG-MARKER', $csv);
    }

    public function test_employee_report_export_excel_applies_data_scope(): void
    {
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
            'employee_code' => 'RPT-A',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptB->id,
            'status' => 'active',
            'employee_code' => 'RPT-B',
        ]);

        Sanctum::actingAs($this->actor->fresh());

        $indexTotal = (int) $this->getJson('/api/v1/employees?per_page=100')
            ->assertOk()
            ->json('meta.total');

        $export = $this->postJson('/api/v1/employees/reports/export/excel', [
            'dimension' => 'department',
            'measure' => 'count',
        ])->assertOk();

        $csv = $export->streamedContent();
        $this->assertStringNotContainsString('Dept B Export', $csv);
        $this->assertStringContainsString('Dept A Export', $csv);

        // CSV değerlerinin toplamı scoped personel sayısına eşit olmalı
        $sum = 0;
        foreach (preg_split("/\r\n|\n|\r/", trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv)) ?: [] as $i => $line) {
            if ($i === 0 || trim($line) === '') {
                continue;
            }
            $parts = str_getcsv($line, ';');
            $sum += (int) ($parts[1] ?? 0);
        }

        $this->assertSame($indexTotal, $sum, 'Report export count sum must equal scoped employee index total');
    }
}
