<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\Lookup;
use App\Models\Module;
use App\Models\User;
use App\Services\LookupService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Faz 4 doğrulama — application_stage hibrit kanban K-A / K-B / geçiş / silme kilidi.
 */
class ApplicationStageKanbanTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private LookupService $lookups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->enableModule($this->company, 'job-applications');

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        $this->admin = $this->admin->fresh();

        $this->lookups = app(LookupService::class);
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug,
                'is_core' => false,
                'is_active' => true,
            ]
        );

        $company->modules()->syncWithoutDetaching([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);
    }

    private function makePosition(): JobPosition
    {
        return JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Yazılım Geliştirici',
            'slug' => 'dev-'.uniqid(),
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
        ]);
    }

    private function makeApplication(string $status = 'new'): JobApplication
    {
        $position = $this->makePosition();

        return JobApplication::create([
            'company_id' => $this->company->id,
            'job_position_id' => $position->id,
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'email' => 'ayse.'.uniqid().'@example.com',
            'status' => $status,
        ]);
    }

    public function test_ka_label_color_change_does_not_mutate_application_status_code(): void
    {
        Sanctum::actingAs($this->admin);

        $app = $this->makeApplication('new');
        $this->assertSame('new', $app->fresh()->status->value);

        $row = Lookup::whereNull('company_id')
            ->where('lookup_type', 'application_stage')
            ->where('value', 'new')
            ->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$row->id}", [
            'label' => 'Yeni Gelenler',
            'color' => '#112233',
        ])->assertOk();

        // K-A: kayıt status kodu aynı
        $this->assertSame('new', $app->fresh()->status->value);

        // Kanban yüzü: forType yeni label/renk
        $column = collect($this->getJson('/api/v1/lookups/application_stage')->json('data'))
            ->firstWhere('value', 'new');
        $this->assertSame('Yeni Gelenler', $column['label']);
        $this->assertSame('#112233', $column['color']);

        $resolved = $this->getJson('/api/v1/lookups-resolve?lookup_type=application_stage&value=new')
            ->assertOk()
            ->json('data');
        $this->assertSame('new', $resolved['value']);
        $this->assertSame('Yeni Gelenler', $resolved['label']);
    }

    public function test_kb_deactivate_stage_keeps_existing_applications_readable(): void
    {
        Sanctum::actingAs($this->admin);

        $app = $this->makeApplication('reviewing');

        $row = Lookup::whereNull('company_id')
            ->where('lookup_type', 'application_stage')
            ->where('value', 'reviewing')
            ->firstOrFail();

        // Hibrit silinemez; pasifleştirme (K-B benzeri)
        $this->deleteJson("/api/v1/lookups-manage/{$row->id}")->assertStatus(403);

        $this->putJson("/api/v1/lookups-manage/{$row->id}", [
            'is_active' => false,
        ])->assertOk();

        $this->assertSame('reviewing', $app->fresh()->status->value);

        // Aktif listede yok (yeni seçim)
        $activeValues = collect($this->getJson('/api/v1/lookups/application_stage')->json('data'))
            ->pluck('value');
        $this->assertFalse($activeValues->contains('reviewing'));

        // Eski kayıt resolve ile görünür
        $resolved = $this->lookups->resolve(
            LookupService::TYPE_APPLICATION_STAGE,
            'reviewing',
            $this->company->id
        );
        $this->assertNotNull($resolved);
        $this->assertSame('reviewing', $resolved['value']);
        $this->assertFalse($resolved['is_active']);

        // Pasif aşamaya yeni geçiş engelli
        $other = $this->makeApplication('new');
        $this->putJson("/api/v1/recruitment/applications/{$other->id}/status", [
            'status' => 'reviewing',
        ])->assertStatus(422);
    }

    public function test_status_transition_updates_system_code_and_rejects_legacy_enum_mismatch(): void
    {
        Sanctum::actingAs($this->admin);

        $app = $this->makeApplication('new');

        $this->putJson("/api/v1/recruitment/applications/{$app->id}/status", [
            'status' => 'shortlisted',
            'notes' => 'Ön elemeyi geçti',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $app->refresh();
        $this->assertSame('shortlisted', $app->status->value);

        $this->assertDatabaseHas('application_status_logs', [
            'job_application_id' => $app->id,
            'from_status' => 'new',
            'to_status' => 'shortlisted',
            'note' => 'Ön elemeyi geçti',
        ]);

        // Eski kırık in: listesi (interview/pool/accepted) artık reddedilmeli
        foreach (['interview', 'testing', 'offer', 'accepted', 'pool'] as $legacy) {
            $this->putJson("/api/v1/recruitment/applications/{$app->id}/status", [
                'status' => $legacy,
            ])->assertStatus(422);
        }

        // Enum ile uyumlu kodlar kabul
        $this->putJson("/api/v1/recruitment/applications/{$app->id}/status", [
            'status' => 'interview_scheduled',
        ])->assertOk();
        $this->assertSame('interview_scheduled', $app->fresh()->status->value);
    }

    public function test_hybrid_application_stage_cannot_add_or_hard_delete_system_code(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'application_stage',
            'value' => 'screening',
            'label' => 'Tarama',
        ])->assertStatus(403);

        $hired = Lookup::whereNull('company_id')
            ->where('lookup_type', 'application_stage')
            ->where('value', 'hired')
            ->firstOrFail();

        $this->deleteJson("/api/v1/lookups-manage/{$hired->id}")->assertStatus(403);

        $this->assertDatabaseHas('lookups', [
            'id' => $hired->id,
            'value' => 'hired',
            'deleted_at' => null,
        ]);
    }
}
