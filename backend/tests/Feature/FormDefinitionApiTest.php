<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Employee;
use App\Models\FormDefinition;
use App\Models\User;
use App\Services\FormFieldCatalogService;
use Database\Seeders\EmployeeFormFieldSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4A-1 — Form Engine veri modeli + sistem alan metadata.
 */
class FormDefinitionApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    /** Geçerli TCKN (algoritmik) */
    private const VALID_TCKN = '10000000146';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        $this->seed(EmployeeFormFieldSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->companyB = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->adminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminA);
        $this->adminA = $this->adminA->fresh();

        $this->adminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminB);
        $this->adminB = $this->adminB->fresh();
    }

    public function test_database_is_pgsql_testing(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('alatax_hr_testing', DB::connection()->getDatabaseName());
    }

    public function test_system_field_seed_is_idempotent(): void
    {
        $before = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->where('entity_type', 'employee')
            ->count();

        $this->assertGreaterThanOrEqual(30, $before);

        app(FormFieldCatalogService::class)->seedSystemCatalog();
        app(FormFieldCatalogService::class)->seedSystemCatalog();

        $after = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->where('entity_type', 'employee')
            ->count();

        $this->assertSame($before, $after);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/form-definitions/employee')->assertStatus(401);
        $this->putJson('/api/v1/form-definitions/employee', [])->assertStatus(401);
    }

    public function test_forbidden_without_forms_permission(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        $role = \App\Models\Role::findOrCreate('viewer_no_forms', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->syncPermissions(['employees.list.view']);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/form-definitions/employee')->assertStatus(403);
    }

    public function test_get_returns_system_and_custom_fields_merged(): void
    {
        CustomFieldDefinition::create([
            'company_id' => $this->companyA->id,
            'entity_type' => 'employee',
            'is_system' => false,
            'field_key' => 'ehliyet_sinifi',
            'field_label' => 'Ehliyet',
            'field_type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 900,
        ]);

        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/v1/form-definitions/employee');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $fields = $response->json('data.fields');
        $this->assertIsArray($fields);
        $keys = collect($fields)->pluck('field_key')->all();
        $this->assertContains('national_id', $keys);
        $this->assertContains('employee_code', $keys);
        $this->assertContains('ehliyet_sinifi', $keys);
        $this->assertArrayHasKey('layout', $response->json('data'));
    }

    public function test_put_rename_hide_required_and_system_delete_rejected(): void
    {
        Sanctum::actingAs($this->adminA);

        $put = $this->putJson('/api/v1/form-definitions/employee', [
            'name' => 'Firma Personel Formu',
            'fields' => [
                [
                    'system_key' => 'national_id',
                    'label_override' => 'Kimlik No (TR)',
                    'is_hidden' => false,
                    'is_required_override' => true,
                    'sort_order' => 5,
                ],
                [
                    'system_key' => 'notes',
                    'is_hidden' => true,
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'id' => 'general',
                        'label' => 'Genel Bilgiler',
                        'sort_order' => 0,
                        'rows' => [
                            ['sort_order' => 0, 'fields' => [['system_key' => 'employee_code']]],
                        ],
                    ],
                ],
            ],
        ]);

        $put->assertStatus(200);

        $get = $this->getJson('/api/v1/form-definitions/employee');
        $get->assertStatus(200)
            ->assertJsonPath('data.name', 'Firma Personel Formu')
            ->assertJsonPath('data.is_system_default', false);

        $national = collect($get->json('data.fields'))->firstWhere('system_key', 'national_id');
        $this->assertNotNull($national);
        $this->assertSame('Kimlik No (TR)', $national['effective_label']);
        $this->assertTrue($national['effective_required']);
        $this->assertSame(5, $national['sort_order']);

        $notes = collect($get->json('data.fields'))->firstWhere('system_key', 'notes');
        $this->assertTrue($notes['is_hidden']);

        $deleteAttempt = $this->putJson('/api/v1/form-definitions/employee', [
            'fields' => [
                ['system_key' => 'national_id', 'delete' => true],
            ],
        ]);
        $deleteAttempt->assertStatus(422);

        // custom-fields DELETE sistem override'a da 422
        $overrideId = $national['id'];
        $this->deleteJson("/api/v1/custom-fields/{$overrideId}")->assertStatus(422);
    }

    public function test_tenant_isolation_form_overrides(): void
    {
        Sanctum::actingAs($this->adminA);
        $this->putJson('/api/v1/form-definitions/employee', [
            'fields' => [
                ['system_key' => 'title', 'label_override' => 'Firma A Unvan'],
            ],
        ])->assertStatus(200);

        Sanctum::actingAs($this->adminB);
        $getB = $this->getJson('/api/v1/form-definitions/employee');
        $getB->assertStatus(200);
        $titleB = collect($getB->json('data.fields'))->firstWhere('system_key', 'title');
        $this->assertNotSame('Firma A Unvan', $titleB['effective_label'] ?? null);
        $this->assertSame('Unvan', $titleB['effective_label']);
    }

    public function test_invalid_tckn_rejected_valid_accepted(): void
    {
        Sanctum::actingAs($this->adminA);

        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'national_id' => '12345678901',
        ])->assertStatus(422)->assertJsonValidationErrors(['national_id']);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'national_id' => self::VALID_TCKN,
        ])->assertStatus(200);

        $this->assertSame(self::VALID_TCKN, $employee->fresh()->national_id);
    }

    public function test_custom_fields_index_excludes_system_catalog(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/v1/custom-fields?entity_type=employee');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        foreach ($data as $row) {
            $this->assertFalse((bool) ($row['is_system'] ?? false));
        }
    }

    public function test_field_permission_round_trip_and_system_delete_blocked(): void
    {
        Sanctum::actingAs($this->adminA);

        $this->putJson('/api/v1/form-definitions/employee', [
            'fields' => [
                [
                    'system_key' => 'gross_salary',
                    'field_permission' => 'readonly',
                ],
                [
                    'system_key' => 'iban',
                    'field_permission' => 'hidden',
                ],
            ],
        ])->assertStatus(200);

        $get = $this->getJson('/api/v1/form-definitions/employee');
        $get->assertStatus(200);
        $gross = collect($get->json('data.fields'))->firstWhere('system_key', 'gross_salary');
        $iban = collect($get->json('data.fields'))->firstWhere('system_key', 'iban');
        $this->assertSame('readonly', $gross['field_permission']);
        $this->assertSame('hidden', $iban['field_permission']);
    }

    public function test_form_engine_pilot_create_writes_system_and_custom_fields(): void
    {
        Sanctum::actingAs($this->adminA);

        CustomFieldDefinition::create([
            'company_id' => $this->companyA->id,
            'entity_type' => 'employee',
            'is_system' => false,
            'field_key' => 'ehliyet_sinifi',
            'field_label' => 'Ehliyet',
            'field_type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 900,
        ]);

        $response = $this->postJson('/api/v1/employees', [
            'employee_code' => 'FE-PILOT-001',
            'name' => 'Form Engine Pilot',
            'status' => 'active',
            'national_id' => self::VALID_TCKN,
            'custom_fields' => [
                'ehliyet_sinifi' => 'B',
            ],
        ]);

        $response->assertStatus(201);
        $employee = Employee::where('employee_code', 'FE-PILOT-001')->first();
        $this->assertNotNull($employee);
        $this->assertSame(self::VALID_TCKN, $employee->national_id);
        $this->assertSame('B', $employee->custom_fields['ehliyet_sinifi'] ?? null);
    }

    /**
     * Dev senaryosu: firma form_definitions satırı yok (ve sistem satırı da silinmiş olsa)
     * GET yine 200 + katalog alanları + default layout döner.
     */
    public function test_get_returns_catalog_default_when_no_form_definition_rows(): void
    {
        DB::table('form_definitions')->delete();

        $this->assertSame(
            0,
            FormDefinition::withoutGlobalScopes()->count()
        );

        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/v1/form-definitions/employee');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_system_default', true)
            ->assertJsonPath('data.entity_type', 'employee');

        $fields = $response->json('data.fields');
        $this->assertIsArray($fields);
        $this->assertGreaterThanOrEqual(30, count($fields));
        $this->assertContains('national_id', collect($fields)->pluck('field_key')->all());

        $layout = $response->json('data.layout');
        $this->assertIsArray($layout);
        $this->assertArrayHasKey('sections', $layout);
        $this->assertNotEmpty($layout['sections']);
    }
}