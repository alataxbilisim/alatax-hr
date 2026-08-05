<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\JobPosition;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-2 — dashboard open_positions Active status regressiyonu.
 */
class DashboardOpenPositionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create([
            'status' => CompanyStatus::Active,
        ]);

        $module = Module::firstOrCreate(
            ['slug' => 'job-applications'],
            ['name' => 'job-applications', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->admin);
    }

    public function test_dashboard_open_positions_counts_active_job_positions(): void
    {
        JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Aktif Pozisyon',
            'slug' => 'aktif-pozisyon-dash',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'published_at' => now(),
        ]);

        JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Kapalı Pozisyon',
            'slug' => 'kapali-pozisyon-dash',
            'status' => JobPositionStatus::Closed,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'published_at' => now(),
        ]);

        Sanctum::actingAs($this->admin->fresh());

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $this->assertGreaterThan(0, $response->json('data.stats.open_positions'));
        $this->assertSame(1, $response->json('data.stats.open_positions'));
    }
}
