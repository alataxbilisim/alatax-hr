<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Module;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4A-3 Zincir 3b — eğitim oturumu yoklama (updateAttendance).
 */
class TrainingAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    private User $noPermUser;

    private TrainingSession $session;

    private User $participantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $trainingModule = Module::firstOrCreate(
            ['slug' => 'training'],
            ['name' => 'Eğitim', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $trainingModule->id => ['is_active' => true, 'activated_at' => now()],
        ]);
        $this->otherCompany->modules()->syncWithoutDetaching([
            $trainingModule->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->admin);

        $this->noPermUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        Employee::factory()->forUser($this->noPermUser)->create([
            'company_id' => $this->company->id,
        ]);

        $this->participantUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($this->participantUser)->create([
            'company_id' => $this->company->id,
        ]);

        $training = Training::create([
            'company_id' => $this->company->id,
            'title' => 'İSG Temel',
            'type' => 'classroom',
            'is_mandatory' => false,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->session = TrainingSession::create([
            'training_id' => $training->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'status' => 'scheduled',
            'created_by' => $this->admin->id,
        ]);

        TrainingParticipant::create([
            'session_id' => $this->session->id,
            'user_id' => $this->participantUser->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    public function test_unauthenticated_attendance_returns_401(): void
    {
        $this->postJson("/api/v1/training/sessions/{$this->session->id}/attendance", [])
            ->assertStatus(401);
    }

    public function test_user_without_permission_gets_403(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->postJson("/api/v1/training/sessions/{$this->session->id}/attendance", [
            'attendances' => [
                [
                    'user_id' => $this->participantUser->id,
                    'status' => 'attended',
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_admin_can_update_attendance(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/training/sessions/{$this->session->id}/attendance", [
            'attendances' => [
                [
                    'user_id' => $this->participantUser->id,
                    'status' => 'attended',
                    'score' => 90,
                    'passed' => true,
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('training_participants', [
            'session_id' => $this->session->id,
            'user_id' => $this->participantUser->id,
            'status' => 'attended',
            'score' => 90,
        ]);
    }

    public function test_cannot_update_other_company_session_attendance(): void
    {
        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);

        Sanctum::actingAs($otherAdmin);

        $this->postJson("/api/v1/training/sessions/{$this->session->id}/attendance", [
            'attendances' => [
                [
                    'user_id' => $this->participantUser->id,
                    'status' => 'attended',
                ],
            ],
        ])->assertStatus(404);
    }
}
