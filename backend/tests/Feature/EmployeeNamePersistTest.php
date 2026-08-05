<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur7 — Ad Soyad create/update DB'ye yazılır ve listede döner (sessiz kayıp yok).
 */
class EmployeeNamePersistTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->admin->fresh());
    }

    public function test_create_without_portal_persists_full_name_and_list_returns_it(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $create = $this->postJson('/api/v1/employees', [
            'employee_code' => 'T7-NAME-01',
            'name' => 'Ayşe Yılmaz',
            'status' => 'active',
            'create_portal_access' => false,
        ])->assertStatus(201);

        $id = (int) ($create->json('data.id') ?? $create->json('data.employee.id'));
        $this->assertGreaterThan(0, $id);

        $this->assertDatabaseHas('employees', [
            'id' => $id,
            'full_name' => 'Ayşe Yılmaz',
        ]);

        $create->assertJsonPath('data.full_name', 'Ayşe Yılmaz');
        $create->assertJsonPath('data.name', 'Ayşe Yılmaz');

        $list = $this->getJson('/api/v1/employees?per_page=50')->assertOk();
        $row = collect($list->json('data.data') ?? $list->json('data'))
            ->firstWhere('id', $id);
        $this->assertNotNull($row);
        $this->assertSame('Ayşe Yılmaz', $row['full_name'] ?? $row['name'] ?? null);
    }

    public function test_update_persists_name_even_without_user(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $employee = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'T7-NAME-02',
            'full_name' => 'Eski Ad',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'name' => 'Yeni Ad Soyad',
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Yeni Ad Soyad')
            ->assertJsonPath('data.name', 'Yeni Ad Soyad');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'full_name' => 'Yeni Ad Soyad',
        ]);
    }

    public function test_update_syncs_linked_user_name(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $linked = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Eski User',
            'type' => UserType::User,
        ]);
        $employee = Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'T7-NAME-03',
            'full_name' => 'Eski User',
            'user_id' => $linked->id,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'name' => 'Senkron Ad',
        ])->assertOk();

        $this->assertSame('Senkron Ad', $linked->fresh()->name);
        $this->assertSame('Senkron Ad', $employee->fresh()->full_name);
    }
}
