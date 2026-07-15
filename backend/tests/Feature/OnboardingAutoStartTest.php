<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\JobApplicationStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\Module;
use App\Models\OnboardingProcess;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ B-5 — hired → convert sonrası onboarding otomatik tetikleme.
 */
class OnboardingAutoStartTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private JobPosition $position;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'demo-firma-b5',
        ]);
        $this->enableModule($this->company, 'job-applications');
        $this->enableModule($this->company, 'onboarding');

        $this->position = JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Ürün Yöneticisi',
            'slug' => 'urun-yoneticisi-b5',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
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

    private function makeAdmin(?Company $company = null): User
    {
        $company ??= $this->company;
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);

        return $admin->fresh();
    }

    private function makeHiredApplication(string $email = 'aday.b5@example.com'): JobApplication
    {
        return JobApplication::create([
            'company_id' => $this->company->id,
            'job_position_id' => $this->position->id,
            'first_name' => 'Aday',
            'last_name' => 'B5',
            'email' => $email,
            'phone' => '05551112233',
            'status' => JobApplicationStatus::Hired,
            'consent_kvkk' => true,
            'consent_at' => now(),
        ]);
    }

    private function createDefaultTemplate(Company $company, array $tasks = []): OnboardingTemplate
    {
        return OnboardingTemplate::create([
            'company_id' => $company->id,
            'name' => 'Varsayılan Onboarding',
            'description' => 'B-5 test',
            'estimated_days' => 14,
            'is_active' => true,
            'is_default' => true,
            'tasks' => $tasks !== [] ? $tasks : [
                [
                    'title' => 'Ekipman teslimi',
                    'description' => 'Laptop + kart',
                    'type' => 'custom',
                    'is_required' => true,
                    'days_offset' => 1,
                ],
                [
                    'title' => 'Oryantasyon',
                    'type' => 'custom',
                    'is_required' => true,
                    'days_offset' => 3,
                ],
            ],
        ]);
    }

    public function test_convert_without_auth_returns_401(): void
    {
        $app = $this->makeHiredApplication('unauth@example.com');
        $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertUnauthorized();
    }

    public function test_convert_without_permission_returns_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $app = $this->makeHiredApplication('noperm@example.com');
        $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertForbidden();
    }

    public function test_hired_convert_starts_default_template_process_with_tasks(): void
    {
        $this->createDefaultTemplate($this->company);
        $admin = $this->makeAdmin();
        $app = $this->makeHiredApplication('hired.ok@example.com');

        Sanctum::actingAs($admin);
        $res = $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertOk();

        $this->assertTrue($res->json('data.created'));
        $this->assertTrue($res->json('data.onboarding.started'));
        $this->assertNull($res->json('data.onboarding.warning'));
        $processId = $res->json('data.onboarding.process_id');
        $this->assertNotNull($processId);

        $employeeUserId = $res->json('data.employee.user_id');
        $this->assertDatabaseHas('onboarding_processes', [
            'id' => $processId,
            'company_id' => $this->company->id,
            'user_id' => $employeeUserId,
            'status' => OnboardingProcess::STATUS_PENDING,
        ]);

        $this->assertSame(2, OnboardingTask::query()->where('process_id', $processId)->count());

        $detail = $this->getJson("/api/v1/recruitment/applications/{$app->id}")->assertOk();
        $this->assertSame($processId, $detail->json('data.onboarding_process.id'));
    }

    public function test_convert_without_template_returns_warning_no_process(): void
    {
        $admin = $this->makeAdmin();
        $app = $this->makeHiredApplication('no.tpl@example.com');

        Sanctum::actingAs($admin);
        $res = $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertOk();

        $this->assertTrue($res->json('data.created'));
        $this->assertFalse($res->json('data.onboarding.started'));
        $this->assertSame('Onboarding şablonu yok', $res->json('data.onboarding.warning'));
        $this->assertNull($res->json('data.onboarding.process_id'));
        $this->assertStringContainsString('Onboarding şablonu yok', (string) $res->json('message'));

        $this->assertSame(
            0,
            OnboardingProcess::query()->where('company_id', $this->company->id)->count()
        );
    }

    public function test_second_convert_does_not_create_second_process(): void
    {
        $this->createDefaultTemplate($this->company);
        $admin = $this->makeAdmin();
        $app = $this->makeHiredApplication('idempotent@example.com');

        Sanctum::actingAs($admin);
        $first = $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertOk();
        $processId = $first->json('data.onboarding.process_id');
        $this->assertTrue($first->json('data.onboarding.started'));

        $second = $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertOk();

        $this->assertFalse($second->json('data.created'));
        $this->assertFalse($second->json('data.onboarding.started'));
        $this->assertTrue($second->json('data.onboarding.skipped'));
        $this->assertSame($processId, $second->json('data.onboarding.process_id'));

        $this->assertSame(
            1,
            OnboardingProcess::query()
                ->where('company_id', $this->company->id)
                ->where('user_id', $first->json('data.employee.user_id'))
                ->count()
        );
    }

    public function test_tenant_a_convert_does_not_create_process_in_tenant_b(): void
    {
        $other = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'other-firma-b5',
        ]);
        $this->enableModule($other, 'job-applications');
        $this->enableModule($other, 'onboarding');
        $this->createDefaultTemplate($other);

        $this->createDefaultTemplate($this->company);

        $adminA = $this->makeAdmin($this->company);
        $app = $this->makeHiredApplication('tenant.a@example.com');

        Sanctum::actingAs($adminA);
        $res = $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertOk();

        $processId = $res->json('data.onboarding.process_id');
        $this->assertNotNull($processId);

        $this->assertDatabaseHas('onboarding_processes', [
            'id' => $processId,
            'company_id' => $this->company->id,
        ]);
        $this->assertSame(
            0,
            OnboardingProcess::query()->where('company_id', $other->id)->count()
        );

        $adminB = $this->makeAdmin($other);
        Sanctum::actingAs($adminB);
        $this->getJson("/api/v1/onboarding/processes/{$processId}")
            ->assertNotFound();
    }

    public function test_manual_process_store_still_works_via_shared_service(): void
    {
        $template = $this->createDefaultTemplate($this->company);
        $admin = $this->makeAdmin();
        $target = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
        $res = $this->postJson('/api/v1/onboarding/processes', [
            'user_id' => $target->id,
            'template_id' => $template->id,
            'title' => 'Manuel süreç',
            'start_date' => now()->toDateString(),
        ])->assertCreated();

        $processId = $res->json('data.id');
        $this->assertSame(2, OnboardingTask::query()->where('process_id', $processId)->count());
    }
}
