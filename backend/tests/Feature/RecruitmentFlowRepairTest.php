<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\JobApplicationStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FAZ B-2 — Public başvuru, manuel aday, hired→personel ön-doldurma.
 */
class RecruitmentFlowRepairTest extends TestCase
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
            'slug' => 'demo-firma-b2',
        ]);
        $this->enableModule($this->company, 'job-applications');

        $this->position = JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Yazılım Geliştirici',
            'slug' => 'yazilim-gelistirici-b2',
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

    private function makeAdmin(): User
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);

        return $admin->fresh();
    }

    public function test_public_apply_happy_creates_new_with_consent(): void
    {
        $res = $this->postJson('/api/v1/public/companies/demo-firma-b2/jobs/yazilim-gelistirici-b2/apply', [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'ali.veli@example.com',
            'phone' => '05551234567',
            'consent_kvkk' => true,
        ])->assertCreated();

        $this->assertSame('new', $res->json('data.status'));

        $this->assertDatabaseHas('job_applications', [
            'company_id' => $this->company->id,
            'job_position_id' => $this->position->id,
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'ali.veli@example.com',
            'consent_kvkk' => true,
            'status' => 'new',
        ]);

        // Kanban listesinde görünür
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $list = $this->getJson('/api/v1/recruitment/applications?per_page=50')->assertOk();
        $names = collect($list->json('data.data') ?? $list->json('data'))
            ->pluck('applicant_name')
            ->all();
        $this->assertTrue(collect($names)->contains(fn ($n) => str_contains((string) $n, 'Ali')));
    }

    public function test_public_apply_without_consent_422(): void
    {
        $this->postJson('/api/v1/public/companies/demo-firma-b2/jobs/yazilim-gelistirici-b2/apply', [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'ali2@example.com',
        ])->assertStatus(422);
    }

    public function test_public_apply_wrong_company_or_inactive_404(): void
    {
        $this->postJson('/api/v1/public/companies/yanlis-slug/jobs/yazilim-gelistirici-b2/apply', [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'x@example.com',
            'consent_kvkk' => true,
        ])->assertNotFound();

        $this->position->update(['status' => JobPositionStatus::Paused]);

        $this->postJson('/api/v1/public/companies/demo-firma-b2/jobs/yazilim-gelistirici-b2/apply', [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'y@example.com',
            'consent_kvkk' => true,
        ])->assertNotFound();
    }

    public function test_public_apply_legacy_route_with_company_slug_body(): void
    {
        $this->postJson('/api/v1/public/jobs/yazilim-gelistirici-b2/apply', [
            'company_slug' => 'demo-firma-b2',
            'first_name' => 'Ayşe',
            'last_name' => 'Demir',
            'email' => 'ayse@example.com',
            'consent_kvkk' => 1,
        ])->assertCreated();
    }

    public function test_hr_manual_store_authorized(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/recruitment/applications', [
            'job_position_id' => $this->position->id,
            'first_name' => 'Manuel',
            'last_name' => 'Aday',
            'email' => 'manuel@example.com',
            'phone' => '05001112233',
            'consent_kvkk' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.first_name', 'Manuel');

        $this->assertDatabaseHas('job_applications', [
            'email' => 'manuel@example.com',
            'source' => 'manual',
            'consent_kvkk' => true,
        ]);
    }

    public function test_hr_manual_store_unauthenticated_401(): void
    {
        $this->postJson('/api/v1/recruitment/applications', [
            'job_position_id' => $this->position->id,
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'x@y.com',
            'consent_kvkk' => true,
        ])->assertUnauthorized();
    }

    public function test_hr_manual_store_unauthorized_403(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $employee->assignRole('employee');

        Sanctum::actingAs($employee);
        $this->postJson('/api/v1/recruitment/applications', [
            'job_position_id' => $this->position->id,
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'x2@y.com',
            'consent_kvkk' => true,
        ])->assertForbidden();
    }

    public function test_hr_manual_store_other_tenant_position_forbidden(): void
    {
        $other = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->enableModule($other, 'job-applications');
        $foreignPosition = JobPosition::create([
            'company_id' => $other->id,
            'title' => 'Foreign',
            'slug' => 'foreign-pos',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
        ]);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/recruitment/applications', [
            'job_position_id' => $foreignPosition->id,
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
            'email' => 'cross@example.com',
            'consent_kvkk' => true,
        ])->assertForbidden();
    }

    public function test_convert_hired_to_employee_prefill_and_idempotent(): void
    {
        $admin = $this->makeAdmin();
        $app = JobApplication::create([
            'company_id' => $this->company->id,
            'job_position_id' => $this->position->id,
            'first_name' => 'Zeynep',
            'last_name' => 'Kara',
            'email' => 'zeynep.kara@example.com',
            'phone' => '05559876543',
            'status' => JobApplicationStatus::Hired,
            'consent_kvkk' => true,
            'consent_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $first = $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertOk();

        $this->assertTrue($first->json('data.created'));
        $employeeId = $first->json('data.employee.id');
        $this->assertNotNull($employeeId);
        $this->assertSame('zeynep.kara@example.com', $first->json('data.employee.personal_email'));
        $this->assertSame('Zeynep Kara', $first->json('data.prefill.name'));
        // Şablonsuz firma: süreç yok, uyarı dönmeli (B-5)
        $this->assertFalse($first->json('data.onboarding.started'));
        $this->assertSame('Onboarding şablonu yok', $first->json('data.onboarding.warning'));

        $this->assertDatabaseHas('employees', [
            'id' => $employeeId,
            'personal_email' => 'zeynep.kara@example.com',
            'personal_phone' => '05559876543',
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('action', 'application_converted_to_employee')
                ->where('model_id', $app->id)
                ->exists()
        );

        $second = $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertOk();

        $this->assertFalse($second->json('data.created'));
        $this->assertSame($employeeId, $second->json('data.employee.id'));
        $this->assertSame(1, Employee::query()->where('personal_email', 'zeynep.kara@example.com')->count());
    }

    public function test_convert_non_hired_rejected(): void
    {
        $admin = $this->makeAdmin();
        $app = JobApplication::create([
            'company_id' => $this->company->id,
            'job_position_id' => $this->position->id,
            'first_name' => 'Bekleyen',
            'last_name' => 'Aday',
            'email' => 'bekleyen@example.com',
            'status' => JobApplicationStatus::New,
            'consent_kvkk' => true,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/recruitment/applications/{$app->id}/convert-to-employee")
            ->assertStatus(422);
    }
}
