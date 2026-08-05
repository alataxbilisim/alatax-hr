<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-3 regresyon: portal profil API'si React'e güvenli düz şekil döner.
 * department nesne olursa FE beyaz ekran (Objects are not valid as a React child).
 */
class PortalProfileContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_profile_department_is_string_not_object(): void
    {
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $dept = Department::create([
            'company_id' => $company->id,
            'name' => 'İnsan Kaynakları',
            'code' => 'IK',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'home_company_id' => $company->id,
            'type' => UserType::User,
            'name' => 'Portal Profil',
        ]);
        $user->assignRole('employee');
        $pos = Position::create([
            'company_id' => $company->id,
            'code' => 'UZM',
            'name' => 'Uzman',
            'is_active' => true,
        ]);
        Employee::factory()->forUser($user)->create([
            'company_id' => $company->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user->fresh());
        $data = $this->getJson('/api/v1/portal/profile')->assertOk()->json('data');

        $this->assertIsString($data['employee']['department'] ?? null);
        $this->assertSame('İnsan Kaynakları', $data['employee']['department']);
        $this->assertIsString($data['employee']['position'] ?? null);
        $this->assertIsArray($data['employee']);
        $this->assertArrayNotHasKey('company_id', $data['employee']);
    }

    public function test_unauthenticated_401(): void
    {
        $this->getJson('/api/v1/portal/profile')->assertUnauthorized();
    }
}
