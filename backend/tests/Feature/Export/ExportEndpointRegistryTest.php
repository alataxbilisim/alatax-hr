<?php

namespace Tests\Feature\Export;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Export\ExportEndpointRegistry;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * DatasetRegistryIsolationTest deseni — her kayıtlı export ucu DataScope sızdırmaz.
 * Registry'ye yeni key eklenip provider güncellenmezse test fail eder.
 */
class ExportEndpointRegistryTest extends TestCase
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
            'name' => 'Reg Dept A',
            'code' => 'RDA',
            'is_active' => true,
        ]);
        $this->deptB = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Reg Dept B',
            'code' => 'RDB',
            'is_active' => true,
        ]);

        $this->actor = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->actor->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
            'employee_code' => 'REG-ACTOR',
        ]);

        $role = Role::findOrCreate('export_registry_dept', 'sanctum');
        $role->forceFill(['data_scope' => 'department', 'panel_access' => true])->save();
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
        $this->actor->assignRole($role);
        $this->actor = $this->actor->fresh();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function registeredExportEndpoints(): array
    {
        return [
            'employees' => ['employees'],
            'users' => ['users'],
            'activity_logs' => ['activity_logs'],
            'employee_reports_excel' => ['employee_reports_excel'],
        ];
    }

    public function test_provider_keys_match_registry(): void
    {
        $registryKeys = app(ExportEndpointRegistry::class)->keys();
        $providerKeys = array_values(array_map(
            static fn (array $row): string => $row[0],
            self::registeredExportEndpoints()
        ));
        sort($registryKeys);
        sort($providerKeys);

        $this->assertSame(
            $registryKeys,
            $providerKeys,
            'ExportEndpointRegistry ile data-provider anahtarları eşleşmeli — yeni export ucu her iki yere de eklenmeli'
        );
    }

    /**
     * @dataProvider registeredExportEndpoints
     */
    public function test_out_of_scope_row_absent_from_export(string $key): void
    {
        $registry = app(ExportEndpointRegistry::class);
        $this->assertTrue($registry->has($key), "Registry'de eksik: {$key}");
        $def = $registry->get($key);

        $this->seedOutOfScopeMarker($key);

        Sanctum::actingAs($this->actor);

        if ($def['method'] === 'POST') {
            $response = $this->postJson($def['path'], [
                'dimension' => 'department',
                'measure' => 'count',
            ]);
        } else {
            $response = $this->get($def['path']);
        }

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringNotContainsString(
            $this->outOfScopeMarker($key),
            $body,
            "Kapsam dışı satır export'ta görünmemeli: {$key}"
        );
        $this->assertStringContainsString(
            $this->inScopeMarker($key),
            $body,
            "Kapsam içi satır export'ta görünmeli: {$key}"
        );
    }

    private function outOfScopeMarker(string $key): string
    {
        return match ($key) {
            'employees' => 'REG-OUT-EMP',
            'users' => 'reg.out.user@test.local',
            'activity_logs' => 'REG-OUT-LOG',
            'employee_reports_excel' => 'Reg Dept B',
            default => '___UNKNOWN_OUT___',
        };
    }

    private function inScopeMarker(string $key): string
    {
        return match ($key) {
            'employees' => 'REG-IN-EMP',
            'users' => 'reg.in.user@test.local',
            'activity_logs' => 'REG-IN-LOG',
            'employee_reports_excel' => 'Reg Dept A',
            default => '___UNKNOWN_IN___',
        };
    }

    private function seedOutOfScopeMarker(string $key): void
    {
        match ($key) {
            'employees' => $this->seedEmployees(),
            'users' => $this->seedUsers(),
            'activity_logs' => $this->seedLogs(),
            'employee_reports_excel' => $this->seedEmployees(),
            default => null,
        };
    }

    private function seedEmployees(): void
    {
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
            'employee_code' => 'REG-IN-EMP',
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptB->id,
            'status' => 'active',
            'employee_code' => 'REG-OUT-EMP',
        ]);
    }

    private function seedUsers(): void
    {
        $panel = Role::findOrCreate('reg_panel', 'sanctum');
        $panel->forceFill(['panel_access' => true, 'data_scope' => 'company'])->save();

        $in = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'reg.in.user@test.local',
            'is_active' => true,
        ]);
        $in->assignRole($panel);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $in->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
        ]);

        $out = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'reg.out.user@test.local',
            'is_active' => true,
        ]);
        $out->assignRole($panel);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $out->id,
            'department_id' => $this->deptB->id,
            'status' => 'active',
        ]);
    }

    private function seedLogs(): void
    {
        $in = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $in->id,
            'department_id' => $this->deptA->id,
            'status' => 'active',
        ]);
        $out = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $out->id,
            'department_id' => $this->deptB->id,
            'status' => 'active',
        ]);

        ActivityLog::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $in->id,
            'user_name' => $in->name,
            'action' => 'update',
            'description' => 'REG-IN-LOG',
            'is_successful' => true,
        ]);
        ActivityLog::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $out->id,
            'user_name' => $out->name,
            'action' => 'update',
            'description' => 'REG-OUT-LOG',
            'is_successful' => true,
        ]);
    }
}
