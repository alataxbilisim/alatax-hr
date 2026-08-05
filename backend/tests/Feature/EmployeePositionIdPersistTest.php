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
 * §4 — position_id is SSOT; string column removed.
 */
class EmployeePositionIdPersistTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_id_persists_through_create_and_show(): void
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

        $pos = Position::create([
            'company_id' => $company->id,
            'code' => 'POS_DEV',
            'name' => 'Yazılım Geliştirici',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin->fresh());

        $resp = $this->postJson('/api/v1/employees', [
            'employee_code' => 'POS-TEST-1',
            'name' => 'Personel Test',
            'position_id' => $pos->id,
            'status' => 'active',
        ])->assertStatus(201);

        $this->assertSame($pos->id, (int) $resp->json('data.position_id'));
        $this->assertSame('Yazılım Geliştirici', $resp->json('data.position_label'));
        $this->assertArrayNotHasKey('position', $resp->json('data'));

        $id = (int) $resp->json('data.id');
        $this->getJson("/api/v1/employees/{$id}")
            ->assertOk()
            ->assertJsonPath('data.employee.position_id', $pos->id)
            ->assertJsonPath('data.employee.position_label', 'Yazılım Geliştirici');
    }

    public function test_position_id_null_is_allowed(): void
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

        $resp = $this->postJson('/api/v1/employees', [
            'employee_code' => 'POS-TEST-NULL',
            'name' => 'Pozisyonsuz',
            'status' => 'active',
        ])->assertStatus(201);

        $this->assertNull($resp->json('data.position_id'));
        $this->assertNull($resp->json('data.position_label'));
    }
}
