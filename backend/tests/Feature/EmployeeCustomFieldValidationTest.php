<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Faz 4 ADIM 4 — Employee custom_fields sunucu validasyonu.
 */
class EmployeeCustomFieldValidationTest extends TestCase
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
        ]);
        $this->assignSpatieAdminRole($this->admin);
        $this->admin = $this->admin->fresh();
    }

    private function createRequiredTextField(string $key = 'ehliyet_sinifi'): CustomFieldDefinition
    {
        return CustomFieldDefinition::create([
            'company_id' => $this->company->id,
            'entity_type' => CustomFieldDefinition::ENTITY_EMPLOYEE,
            'field_key' => $key,
            'field_label' => 'Ehliyet Sınıfı',
            'field_type' => CustomFieldDefinition::TYPE_TEXT,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_unauthenticated_employee_update_returns_401(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'custom_fields' => [],
        ])->assertStatus(401);
    }

    public function test_update_rejects_missing_required_custom_field(): void
    {
        $this->createRequiredTextField();
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'custom_fields' => null,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'custom_fields' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['custom_fields.ehliyet_sinifi']);
    }

    public function test_store_rejects_missing_required_custom_field(): void
    {
        $this->createRequiredTextField();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-CF-001',
            'name' => 'Test Personel',
            'status' => 'active',
            'custom_fields' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['custom_fields.ehliyet_sinifi']);
    }

    public function test_update_accepts_required_custom_field_value(): void
    {
        $this->createRequiredTextField();
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'custom_fields' => [
                'ehliyet_sinifi' => 'B',
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
        ]);
        $employee->refresh();
        $this->assertSame('B', $employee->custom_fields['ehliyet_sinifi'] ?? null);
    }

    public function test_update_rejects_invalid_select_option(): void
    {
        CustomFieldDefinition::create([
            'company_id' => $this->company->id,
            'entity_type' => CustomFieldDefinition::ENTITY_EMPLOYEE,
            'field_key' => 'kan_grubu_ozel',
            'field_label' => 'Kan Grubu Özel',
            'field_type' => CustomFieldDefinition::TYPE_SELECT,
            'field_options' => [
                ['value' => 'A+', 'label' => 'A Pozitif'],
                ['value' => 'B+', 'label' => 'B Pozitif'],
            ],
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'custom_fields' => [
                'kan_grubu_ozel' => 'Z+',
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_fields.kan_grubu_ozel']);
    }
}
