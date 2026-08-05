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
 * Tur8 — aynı adlı iki pozisyon code ile ayırt edilir (employees.position = code).
 */
class EmployeePositionCodePersistTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_named_positions_assigned_by_code_remain_distinct(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin->fresh());

        Position::create([
            'company_id' => $company->id,
            'code' => 'DEMO_POS_07',
            'name' => 'Kıdemli Yazılım Geliştirici',
            'is_active' => true,
        ]);
        Position::create([
            'company_id' => $company->id,
            'code' => 'YAZ_KID',
            'name' => 'Kıdemli Yazılım Geliştirici',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin->fresh());

        $a = $this->postJson('/api/v1/employees', [
            'employee_code' => 'T8-POS-A',
            'name' => 'Personel A',
            'position' => 'DEMO_POS_07',
            'status' => 'active',
        ])->assertStatus(201);

        $b = $this->postJson('/api/v1/employees', [
            'employee_code' => 'T8-POS-B',
            'name' => 'Personel B',
            'position' => 'YAZ_KID',
            'status' => 'active',
        ])->assertStatus(201);

        $idA = (int) ($a->json('data.id') ?? 0);
        $idB = (int) ($b->json('data.id') ?? 0);

        $this->assertSame('DEMO_POS_07', $a->json('data.position'));
        $this->assertSame('YAZ_KID', $b->json('data.position'));
        $this->assertSame('Kıdemli Yazılım Geliştirici', $a->json('data.position_label'));
        $this->assertSame('Kıdemli Yazılım Geliştirici', $b->json('data.position_label'));

        $this->getJson("/api/v1/employees/{$idA}")
            ->assertOk()
            ->assertJsonPath('data.employee.position', 'DEMO_POS_07')
            ->assertJsonPath('data.employee.position_label', 'Kıdemli Yazılım Geliştirici');
        $this->getJson("/api/v1/employees/{$idB}")
            ->assertOk()
            ->assertJsonPath('data.employee.position', 'YAZ_KID')
            ->assertJsonPath('data.employee.position_label', 'Kıdemli Yazılım Geliştirici');

        $this->assertNotSame(
            $a->json('data.position'),
            $b->json('data.position'),
            'Aynı adlı pozisyonlar code ile ayırt edilmeli'
        );
    }
}
