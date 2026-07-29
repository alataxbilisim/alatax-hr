<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\SettingValue;
use App\Models\User;
use App\Services\LeaveCalculationService;
use App\Services\Settings\Settings;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D4a — Settings Registry: çözümleme, yasal taban, yetki, tenant, audit, pilot davranış.
 */
class SettingsRegistryD4aTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function makeAdmin(Company $company): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);

        return $this->assignSpatieAdminRole($user);
    }

    private function makePlainUser(Company $company): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::User,
        ]);
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/settings/registry')->assertStatus(401);
    }

    public function test_unauthorized_role_gets_403(): void
    {
        $user = $this->makePlainUser($this->company);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/settings/registry')->assertStatus(403);
    }

    public function test_resolution_order_user_dept_branch_company_default(): void
    {
        $admin = $this->makeAdmin($this->company);
        $dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'İK',
            'code' => 'HR',
            'is_active' => true,
        ]);
        $branch = \App\Models\Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Merkez',
            'code' => 'HQ',
            'is_active' => true,
        ]);
        $branchId = $branch->id;

        $target = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $target->id,
            'department_id' => $dept->id,
            'branch_id' => $branchId,
        ]);

        Sanctum::actingAs($admin);
        $key = 'leaves.request.min_days_notice';

        // varsayılan
        $this->assertSame(0, (int) Settings::get($key, ['company_id' => $this->company->id]));

        SettingValue::create([
            'company_id' => $this->company->id,
            'scope_type' => 'company',
            'scope_id' => null,
            'key' => $key,
            'value' => SettingValue::wrapScalar(3),
            'updated_by' => $admin->id,
        ]);
        app(\App\Services\Settings\SettingsResolver::class)->invalidateCompany($this->company->id);
        $this->assertSame(3, (int) Settings::get($key, [
            'company_id' => $this->company->id,
            'user_id' => $target->id,
        ]));

        SettingValue::create([
            'company_id' => $this->company->id,
            'scope_type' => 'branch',
            'scope_id' => $branchId,
            'key' => $key,
            'value' => SettingValue::wrapScalar(5),
            'updated_by' => $admin->id,
        ]);
        app(\App\Services\Settings\SettingsResolver::class)->invalidateCompany($this->company->id);
        $this->assertSame(5, (int) Settings::get($key, [
            'company_id' => $this->company->id,
            'user_id' => $target->id,
        ]));

        SettingValue::create([
            'company_id' => $this->company->id,
            'scope_type' => 'department',
            'scope_id' => $dept->id,
            'key' => $key,
            'value' => SettingValue::wrapScalar(7),
            'updated_by' => $admin->id,
        ]);
        app(\App\Services\Settings\SettingsResolver::class)->invalidateCompany($this->company->id);
        $this->assertSame(7, (int) Settings::get($key, [
            'company_id' => $this->company->id,
            'user_id' => $target->id,
        ]));

        SettingValue::create([
            'company_id' => $this->company->id,
            'scope_type' => 'user',
            'scope_id' => $target->id,
            'key' => $key,
            'value' => SettingValue::wrapScalar(9),
            'updated_by' => $admin->id,
        ]);
        app(\App\Services\Settings\SettingsResolver::class)->invalidateCompany($this->company->id);
        $this->assertSame(9, (int) Settings::get($key, [
            'company_id' => $this->company->id,
            'user_id' => $target->id,
        ]));
    }

    public function test_legal_min_rejects_below_floor(): void
    {
        $admin = $this->makeAdmin($this->company);
        Sanctum::actingAs($admin);

        // Sistem yasal asgari (SuperAdmin gerekmez — admin put system reddedilir; seed default=12)
        // Firma retention 11 < legal 12 → 422
        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => 'leaves.retention.months', 'value' => 11],
            ],
        ])->assertStatus(422)->assertJsonFragment(['Yasal asgari 12.']);

        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => 'leaves.retention.months', 'value' => 24],
            ],
        ])->assertStatus(200);
    }

    public function test_setting_permission_hides_from_registry(): void
    {
        $user = $this->makePlainUser($this->company);
        Permission::findOrCreate('settings.values.view', 'sanctum');
        Permission::findOrCreate('settings.values.edit', 'sanctum');
        // leaves yok — reports var
        Permission::findOrCreate('settings.reports.view', 'sanctum');
        Permission::findOrCreate('settings.reports.edit', 'sanctum');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo([
            'settings.values.view',
            'settings.values.edit',
            'settings.reports.view',
            'settings.reports.edit',
        ]);
        Sanctum::actingAs($user);

        $keys = collect($this->getJson('/api/v1/settings/registry')->assertStatus(200)->json('data'))
            ->pluck('key');
        $this->assertTrue($keys->contains('reports.privacy.min_cell_threshold'));
        $this->assertFalse($keys->contains('leaves.balance.allow_negative'));

        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => 'leaves.balance.allow_negative', 'value' => true],
            ],
        ])->assertStatus(403);
    }

    public function test_tenant_isolation_and_audit(): void
    {
        $admin = $this->makeAdmin($this->company);
        $otherAdmin = $this->makeAdmin($this->otherCompany);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => 'leaves.balance.allow_carryover', 'value' => false],
            ],
        ])->assertStatus(200);

        $this->assertFalse((bool) Settings::get('leaves.balance.allow_carryover', [
            'company_id' => $this->company->id,
        ]));
        $this->assertTrue((bool) Settings::get('leaves.balance.allow_carryover', [
            'company_id' => $this->otherCompany->id,
        ]));

        Sanctum::actingAs($otherAdmin);
        $this->assertTrue((bool) Settings::get('leaves.balance.allow_carryover', [
            'company_id' => $this->otherCompany->id,
        ]));

        $row = SettingValue::query()
            ->where('company_id', $this->company->id)
            ->where('key', 'leaves.balance.allow_carryover')
            ->first();
        $this->assertNotNull($row);

        $morph = $row->getMorphClass();
        $log = ActivityLog::withoutGlobalScopes()
            ->where('model_id', $row->id)
            ->where(function ($q) use ($morph) {
                $q->where('model_type', SettingValue::class)
                    ->orWhere('model_type', $morph);
            })
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
    }

    public function test_reset_and_profile_export_import(): void
    {
        $admin = $this->makeAdmin($this->company);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => 'reports.cache.default_ttl_seconds', 'value' => 120],
            ],
        ])->assertStatus(200);

        $profile = $this->getJson('/api/v1/settings/profile')->assertStatus(200)->json('data');
        $this->assertNotEmpty($profile['values']);

        $this->postJson('/api/v1/settings/values/reset', [
            'key' => 'reports.cache.default_ttl_seconds',
            'scope_type' => 'company',
        ])->assertStatus(200);

        $this->assertSame(300, (int) Settings::get('reports.cache.default_ttl_seconds', [
            'company_id' => $this->company->id,
        ]));

        $this->postJson('/api/v1/settings/profile', [
            'values' => $profile['values'],
        ])->assertStatus(200);

        $this->assertSame(120, (int) Settings::get('reports.cache.default_ttl_seconds', [
            'company_id' => $this->company->id,
        ]));
    }

    public function test_pilot_allow_negative_changes_balance_check(): void
    {
        $admin = $this->makeAdmin($this->company);
        $employeeUser = $this->makePlainUser($this->company);
        $leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Yıllık',
            'code' => 'YL',
            'is_active' => true,
            'default_days' => 14,
        ]);
        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $employeeUser->id,
            'leave_type_id' => $leaveType->id,
            'year' => (int) now()->year,
            'total_days' => 2,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $svc = app(LeaveCalculationService::class);
        $before = $svc->checkBalance($employeeUser->id, $leaveType->id, 5, (int) now()->year);
        $this->assertFalse($before['sufficient']);

        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => 'leaves.balance.allow_negative', 'value' => true],
            ],
        ])->assertStatus(200);

        app(\App\Services\Settings\SettingsResolver::class)->invalidateCompany($this->company->id);
        $after = $svc->checkBalance($employeeUser->id, $leaveType->id, 5, (int) now()->year);
        $this->assertTrue($after['sufficient']);
    }

    public function test_report_privacy_legacy_bridge(): void
    {
        $this->company->setSetting('report_privacy.min_cell_threshold', 8);
        $this->assertSame(8, (int) Settings::get('reports.privacy.min_cell_threshold', [
            'company_id' => $this->company->id,
        ]));

        $admin = $this->makeAdmin($this->company);
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => 'reports.privacy.min_cell_threshold', 'value' => 6],
            ],
        ])->assertStatus(200);

        $this->company->refresh();
        $this->assertSame(6, (int) $this->company->getSetting('report_privacy.min_cell_threshold'));
    }
}
