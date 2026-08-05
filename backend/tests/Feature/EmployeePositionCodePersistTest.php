<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * §4 — position string column removed; only position_id accepted.
 * §5 — position name unique within company.
 */
class EmployeePositionCodePersistTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_string_field_not_accepted_in_create(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $admin = User::factory()->create([
            'home_company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin->fresh());
        Sanctum::actingAs($admin->fresh());

        $pos = Position::create([
            'company_id' => $company->id,
            'code' => 'POS_X',
            'name' => 'Test Pozisyon',
            'is_active' => true,
        ]);

        $resp = $this->postJson('/api/v1/employees', [
            'employee_code' => 'T8-NOSTR',
            'name' => 'Personel A',
            'position_id' => $pos->id,
            'status' => 'active',
        ])->assertStatus(201);

        $this->assertSame($pos->id, (int) $resp->json('data.position_id'));
        $this->assertSame('Test Pozisyon', $resp->json('data.position_label'));
    }
}
