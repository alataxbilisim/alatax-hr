<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\ApplicationForm;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\Module;
use App\Models\User;
use App\Services\FormFieldCatalogService;
use Database\Seeders\JobApplicationFormFieldSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4A-4 — job_application Form Engine + public kariyer formu.
 */
class JobApplicationFormEngineTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private JobPosition $positionWithForm;

    private JobPosition $positionWithoutForm;

    private ApplicationForm $applicationForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        $this->seed(JobApplicationFormFieldSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->companyA = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'firma-a-4a4',
        ]);
        $this->companyB = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'firma-b-4a4',
        ]);

        $this->enableModule($this->companyA, 'job-applications');
        $this->enableModule($this->companyB, 'job-applications');

        $this->adminA = $this->makeAdmin($this->companyA);
        $this->adminB = $this->makeAdmin($this->companyB);

        $this->applicationForm = ApplicationForm::create([
            'company_id' => $this->companyA->id,
            'name' => 'İşe Alım Ek Form',
            'fields' => [
                [
                    'id' => 'expected_salary',
                    'type' => 'number',
                    'label' => 'Beklenen Maaş',
                    'required' => true,
                ],
                [
                    'id' => 'referral_source',
                    'type' => 'select',
                    'label' => 'Kaynak',
                    'required' => false,
                    'options' => [
                        'linkedin' => 'LinkedIn',
                        'other' => 'Diğer',
                    ],
                ],
            ],
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->positionWithForm = JobPosition::create([
            'company_id' => $this->companyA->id,
            'title' => 'Formlu Pozisyon',
            'slug' => 'formlu-pozisyon-4a4',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'form_id' => $this->applicationForm->id,
            'published_at' => now(),
        ]);

        $this->positionWithoutForm = JobPosition::create([
            'company_id' => $this->companyA->id,
            'title' => 'Formsuz Pozisyon',
            'slug' => 'formsuz-pozisyon-4a4',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'form_id' => null,
            'published_at' => now(),
        ]);
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

    private function makeAdmin(Company $company): User
    {
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);

        return $admin->fresh();
    }

    public function test_catalog_seed_is_idempotent(): void
    {
        $before = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('entity_type', CustomFieldDefinition::ENTITY_JOB_APPLICATION)
            ->where('is_system', true)
            ->count();

        $this->assertGreaterThanOrEqual(7, $before);

        app(FormFieldCatalogService::class)->seedSystemCatalog();
        app(FormFieldCatalogService::class)->seedSystemCatalog();

        $after = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('entity_type', CustomFieldDefinition::ENTITY_JOB_APPLICATION)
            ->where('is_system', true)
            ->count();

        $this->assertSame($before, $after);
    }

    public function test_form_definitions_job_application_auth_and_tenant(): void
    {
        $this->getJson('/api/v1/form-definitions/job_application')->assertStatus(401);

        $noPerm = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($noPerm);
        $this->getJson('/api/v1/form-definitions/job_application')->assertStatus(403);

        Sanctum::actingAs($this->adminA);
        $this->getJson('/api/v1/form-definitions/job_application')
            ->assertOk()
            ->assertJsonPath('data.entity_type', 'job_application');

        Sanctum::actingAs($this->adminA);
        $this->putJson('/api/v1/form-definitions/job_application', [
            'name' => 'Firma A Başvuru',
            'fields' => [
                [
                    'system_key' => 'first_name',
                    'label_override' => 'Adınız',
                ],
            ],
        ])->assertOk()->assertJsonPath('data.name', 'Firma A Başvuru');

        Sanctum::actingAs($this->adminB);
        $res = $this->getJson('/api/v1/form-definitions/job_application')->assertOk();
        $labels = collect($res->json('data.fields'))->pluck('effective_label', 'field_key');
        $this->assertNotSame('Adınız', $labels->get('first_name'));
    }

    public function test_public_form_active_ok_inactive_404_tenant_isolated(): void
    {
        $this->getJson('/api/v1/public/companies/firma-a-4a4/jobs/formlu-pozisyon-4a4/form')
            ->assertOk()
            ->assertJsonPath('data.has_custom_form', true)
            ->assertJsonMissingPath('data.definition.fields.0.internal_notes');

        $payload = $this->getJson('/api/v1/public/companies/firma-a-4a4/jobs/formlu-pozisyon-4a4/form')->json('data');
        $keys = collect($payload['definition']['fields'])->pluck('field_key')->all();
        $this->assertContains('consent_kvkk', $keys);
        $this->assertContains('expected_salary', $keys);
        $this->assertNotContains('internal_notes', $keys);

        JobPosition::where('id', $this->positionWithForm->id)->update([
            'status' => JobPositionStatus::Closed,
        ]);

        $this->getJson('/api/v1/public/companies/firma-a-4a4/jobs/formlu-pozisyon-4a4/form')
            ->assertStatus(404);

        JobPosition::where('id', $this->positionWithForm->id)->update([
            'status' => JobPositionStatus::Active,
        ]);

        // Yanlış tenant slug → 404 (izolasyon)
        $this->getJson('/api/v1/public/companies/firma-b-4a4/jobs/formlu-pozisyon-4a4/form')
            ->assertStatus(404);
    }

    public function test_public_submit_with_form_writes_columns_and_form_data(): void
    {
        Storage::fake('public');

        $this->postJson('/api/v1/public/companies/firma-a-4a4/jobs/formlu-pozisyon-4a4/apply', [
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'email' => 'ayse@example.com',
            'consent_kvkk' => true,
            'form_data' => [
                'expected_salary' => 45000,
                'referral_source' => 'linkedin',
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('job_applications', [
            'company_id' => $this->companyA->id,
            'job_position_id' => $this->positionWithForm->id,
            'first_name' => 'Ayşe',
            'email' => 'ayse@example.com',
            'consent_kvkk' => true,
        ]);

        $app = JobApplication::where('email', 'ayse@example.com')->first();
        $this->assertNotNull($app);
        $this->assertSame(45000, (int) ($app->form_data['expected_salary'] ?? 0));
        $this->assertSame('linkedin', $app->form_data['referral_source'] ?? null);
    }

    public function test_public_submit_without_consent_422(): void
    {
        $this->postJson('/api/v1/public/companies/firma-a-4a4/jobs/formlu-pozisyon-4a4/apply', [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'ali@example.com',
            'form_data' => ['expected_salary' => 1],
        ])->assertStatus(422);
    }

    public function test_public_submit_invalid_file_type_422(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/public/companies/firma-a-4a4/jobs/formlu-pozisyon-4a4/apply', [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'ali.file@example.com',
            'consent_kvkk' => '1',
            'form_data' => ['expected_salary' => 10],
            'cv' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
        ])->assertStatus(422);
    }

    public function test_public_submit_without_form_keeps_b2_behavior(): void
    {
        $this->postJson('/api/v1/public/companies/firma-a-4a4/jobs/formsuz-pozisyon-4a4/apply', [
            'first_name' => 'Can',
            'last_name' => 'Demir',
            'email' => 'can@example.com',
            'consent_kvkk' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('job_applications', [
            'job_position_id' => $this->positionWithoutForm->id,
            'email' => 'can@example.com',
            'consent_kvkk' => true,
        ]);
    }

    public function test_public_required_form_field_missing_422(): void
    {
        $this->postJson('/api/v1/public/companies/firma-a-4a4/jobs/formlu-pozisyon-4a4/apply', [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'ali.req@example.com',
            'consent_kvkk' => true,
            'form_data' => [],
        ])->assertStatus(422);
    }

    public function test_public_routes_use_throttle_middleware(): void
    {
        $route = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->first(fn ($r) => str_contains($r->uri(), 'public/companies/{companySlug}/jobs/{positionSlug}/form'));

        $this->assertNotNull($route);
        $middlewares = $route->gatherMiddleware();
        $this->assertTrue(
            collect($middlewares)->contains(fn ($m) => is_string($m) && str_contains($m, 'throttle:public'))
        );
    }

    public function test_manual_candidate_with_form_data(): void
    {
        Sanctum::actingAs($this->adminA);

        $this->postJson('/api/v1/recruitment/applications', [
            'job_position_id' => $this->positionWithForm->id,
            'first_name' => 'Manuel',
            'last_name' => 'Aday',
            'email' => 'manuel@example.com',
            'consent_kvkk' => true,
            'form_data' => [
                'expected_salary' => 50000,
            ],
        ])->assertCreated();

        $app = JobApplication::where('email', 'manuel@example.com')->first();
        $this->assertNotNull($app);
        $this->assertSame(50000, (int) ($app->form_data['expected_salary'] ?? 0));
    }

    public function test_database_is_pgsql_testing(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('alatax_hr_testing', DB::connection()->getDatabaseName());
    }
}
