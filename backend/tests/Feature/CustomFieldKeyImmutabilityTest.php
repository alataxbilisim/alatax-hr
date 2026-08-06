<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\Datasets\EmployeesDataset;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Özel alan field_key değişmezliği (API prohibited + model guard).
 */
class CustomFieldKeyImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        $this->admin = $this->admin->fresh();
    }

    private function makeField(string $key = 'probe_key'): CustomFieldDefinition
    {
        return CustomFieldDefinition::create([
            'company_id' => $this->company->id,
            'entity_type' => CustomFieldDefinition::ENTITY_EMPLOYEE,
            'field_key' => $key,
            'field_label' => 'Probe Label',
            'field_type' => CustomFieldDefinition::TYPE_TEXT,
            'is_required' => false,
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_update_rejects_field_key_change_via_api(): void
    {
        $field = $this->makeField('stable_key');

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/custom-fields/{$field->id}", [
            'field_key' => 'renamed_key',
            'field_label' => 'Still Same Label',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['field_key']);

        $this->assertSame('stable_key', $field->fresh()->field_key);
    }

    public function test_update_allows_label_change_and_report_column_stable(): void
    {
        $field = $this->makeField('report_stable');
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'custom_fields' => ['report_stable' => 'keep-me'],
        ]);

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/custom-fields/{$field->id}", [
            'field_label' => 'Yeni Etiket',
        ])->assertStatus(200);

        $field->refresh();
        $this->assertSame('report_stable', $field->field_key);
        $this->assertSame('Yeni Etiket', $field->field_label);

        $employee->refresh();
        $this->assertSame('keep-me', $employee->custom_fields['report_stable'] ?? null);

        $keys = collect((new EmployeesDataset)->fieldsForCompany($this->company->id))
            ->map(fn ($f) => $f->key)
            ->all();
        $this->assertContains('cf_report_stable', $keys);
    }

    public function test_model_rejects_field_key_mutation(): void
    {
        $field = $this->makeField('locked_key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('field_key is immutable');

        $field->field_key = 'should_fail';
        $field->save();
    }
}
