<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Organization;
use App\Models\SettingValue;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\Settings\SettingsResolver;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur5 — Settings yazma aktif bağlamı (Otel C) takip eder; home A'ya sessiz yazılmaz.
 */
class SettingsWriterActiveContextTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org SW', 'slug' => 'org-sw-t5']);
        $this->companyA = Company::factory()->create([
            'name' => 'Demo Firma AŞ',
            'slug' => 'sw-a-t5',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'Demo Otel C',
            'slug' => 'sw-b-t5',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);

        $this->userA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assignSpatieAdminRole($this->userA->fresh());
        app(CompanyContextService::class)->ensureMembership($this->userA, (int) $this->companyB->id, false);
    }

    public function test_settings_write_follows_active_context_not_home(): void
    {
        $key = 'leaves.balance.allow_carryover';

        Sanctum::actingAs($this->userA);

        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [
                ['key' => $key, 'value' => true],
            ],
        ], ['X-Company-Id' => (string) $this->companyB->id])
            ->assertOk();

        $this->assertDatabaseHas('setting_values', [
            'company_id' => $this->companyB->id,
            'key' => $key,
            'scope_type' => 'company',
        ]);
        $this->assertDatabaseMissing('setting_values', [
            'company_id' => $this->companyA->id,
            'key' => $key,
        ]);

        app(SettingsResolver::class)->invalidateCompany($this->companyB->id);
        $row = SettingValue::query()
            ->where('company_id', $this->companyB->id)
            ->where('key', $key)
            ->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->scalarValue());
    }
}
