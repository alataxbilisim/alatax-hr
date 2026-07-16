<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\ApplicationForm;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\FormDefinition;
use App\Models\JobPosition;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\JobApplicationFormFieldSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * C6 — form_definition_id köprüsü + sidebar BE permission smoke.
 */
class DebtCleanupC6Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        $this->seed(JobApplicationFormFieldSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'c6-firma',
        ]);
        $module = Module::firstOrCreate(
            ['slug' => 'job-applications'],
            ['name' => 'job-applications', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
    }

    public function test_sidebar_module_endpoints_auth_and_permission(): void
    {
        $paths = [
            '/api/v1/onboarding/templates',
            '/api/v1/performance/periods',
            '/api/v1/performance/criteria',
            '/api/v1/training/sessions',
            '/api/v1/assets/categories',
        ];

        foreach (['onboarding', 'performance', 'training', 'asset-management'] as $slug) {
            $this->enableModule($this->company, $slug);
        }

        foreach ($paths as $path) {
            $this->getJson($path)->assertUnauthorized();
        }

        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($viewer);
        foreach ($paths as $path) {
            $this->getJson($path)->assertForbidden();
        }

        Sanctum::actingAs($this->admin->fresh());
        foreach ($paths as $path) {
            $this->getJson($path)->assertSuccessful();
        }
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_core' => false, 'is_active' => true]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);
    }

    public function test_position_with_form_definition_renders_public_formengine(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $defRes = $this->getJson('/api/v1/form-definitions/job_application')->assertOk();
        $defId = (int) $defRes->json('data.id');
        $this->assertGreaterThan(0, $defId);

        CustomFieldDefinition::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'entity_type' => CustomFieldDefinition::ENTITY_JOB_APPLICATION,
            'field_key' => 'portfolio_url',
            'field_label' => 'Portfolyo',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 50,
        ]);

        $create = $this->postJson('/api/v1/recruitment/positions', [
            'title' => 'FE Köprü Pozisyon',
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'form_definition_id' => $defId,
            'status' => 'active',
        ])->assertCreated();

        $posId = (int) $create->json('data.id');
        $this->assertDatabaseHas('job_positions', [
            'id' => $posId,
            'form_definition_id' => $defId,
            'form_id' => null,
        ]);

        $slug = (string) $create->json('data.slug');
        JobPosition::whereKey($posId)->update([
            'status' => JobPositionStatus::Active,
            'published_at' => now(),
        ]);

        $public = $this->getJson("/api/v1/public/companies/c6-firma/jobs/{$slug}/form")
            ->assertOk()
            ->assertJsonPath('data.has_custom_form', true)
            ->assertJsonPath('data.form_source', 'form_definition');

        $keys = collect($public->json('data.definition.fields'))->pluck('field_key');
        $this->assertTrue($keys->contains('consent_kvkk'));
        $this->assertTrue($keys->contains('portfolio_url'));
    }

    public function test_legacy_form_id_position_still_works(): void
    {
        $legacy = ApplicationForm::create([
            'company_id' => $this->company->id,
            'name' => 'Legacy C6',
            'fields' => [
                ['id' => 'expected_salary', 'type' => 'number', 'label' => 'Maaş', 'required' => true],
            ],
            'is_active' => true,
        ]);

        $position = JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Legacy Pozisyon',
            'slug' => 'legacy-c6-pos',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'form_id' => $legacy->id,
            'form_definition_id' => null,
            'published_at' => now(),
        ]);

        $public = $this->getJson('/api/v1/public/companies/c6-firma/jobs/legacy-c6-pos/form')
            ->assertOk()
            ->assertJsonPath('data.has_custom_form', true)
            ->assertJsonPath('data.form_source', 'application_form');

        $keys = collect($public->json('data.definition.fields'))->pluck('field_key');
        $this->assertTrue($keys->contains('expected_salary'));
        $this->assertNotNull($position->id);
    }

    public function test_form_definition_rows_exist_for_job_application(): void
    {
        $count = FormDefinition::withoutGlobalScopes()
            ->where('entity_type', 'job_application')
            ->count();
        $this->assertGreaterThan(0, $count);
    }
}
