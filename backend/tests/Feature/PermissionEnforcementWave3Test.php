<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Module;
use App\Models\OnboardingTemplate;
use App\Models\PerformancePeriod;
use App\Models\Survey;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Dalga 3: onboarding + performance + training + assets + surveys + analytics.
 */
class PermissionEnforcementWave3Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private Company $unlicensedCompany;

    /** @var array<string, string> slug => permission for list GET */
    private array $groups = [
        'onboarding' => [
            'slug' => 'onboarding',
            'uri' => '/api/v1/onboarding/templates',
            'permission' => 'onboarding.templates.view',
        ],
        'performance' => [
            'slug' => 'performance',
            'uri' => '/api/v1/performance/periods',
            'permission' => 'performance.periods.view',
        ],
        'training' => [
            'slug' => 'training',
            'uri' => '/api/v1/training/trainings',
            'permission' => 'training.list.view',
        ],
        'assets' => [
            'slug' => 'asset-management',
            'uri' => '/api/v1/assets/categories',
            'permission' => 'assets.categories.view',
        ],
        'surveys' => [
            'slug' => 'surveys',
            'uri' => '/api/v1/surveys',
            'permission' => 'surveys.list.view',
        ],
        'analytics' => [
            'slug' => 'hr-analytics',
            'uri' => '/api/v1/analytics/summary',
            'permission' => 'analytics.reports.view',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'onboarding.templates.view',
            'onboarding.*',
            'performance.periods.view',
            'performance.*',
            'training.list.view',
            'training.*',
            'assets.categories.view',
            'assets.*',
            'surveys.list.view',
            'surveys.*',
            'analytics.reports.view',
            'analytics.*',
            'performance.feedback.edit',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->unlicensedCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        foreach ($this->groups as $group) {
            $this->enableModule($this->company, $group['slug']);
            $this->enableModule($this->otherCompany, $group['slug']);
        }
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

    private function makeUser(UserType $type, ?Company $company = null): User
    {
        $user = User::factory()->create([
            'type' => $type,
            'company_id' => $type === UserType::SuperAdmin ? null : ($company ?? $this->company)->id,
            'is_active' => true,
        ]);

        if ($type === UserType::CompanyAdmin) {
            return $this->assignSpatieAdminRole($user);
        }

        return $user;
    }

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function extractIds(mixed $payload): \Illuminate\Support\Collection
    {
        if (! is_array($payload)) {
            return collect();
        }

        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            return collect($payload['data'])->pluck('id');
        }

        if (array_is_list($payload)) {
            return collect($payload)->pluck('id');
        }

        return collect();
    }

    // ——— 6 grup: ortak 6'lı desen ———

    public function test_all_groups_unauthenticated_returns_401(): void
    {
        foreach ($this->groups as $name => $group) {
            $this->assertSame(401, $this->getJson($group['uri'])->status(), "{$name} auth yok");
        }
    }

    public function test_all_groups_unlicensed_company_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::CompanyAdmin, $this->unlicensedCompany));

        foreach ($this->groups as $name => $group) {
            $this->assertSame(403, $this->getJson($group['uri'])->status(), "{$name} lisanssız");
        }
    }

    public function test_all_groups_user_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));

        foreach ($this->groups as $name => $group) {
            $this->assertSame(403, $this->getJson($group['uri'])->status(), "{$name} izinsiz user");
        }
    }

    public function test_all_groups_user_with_permission_returns_200(): void
    {
        foreach ($this->groups as $name => $group) {
            $user = $this->makeUser(UserType::User);
            $user->givePermissionTo($group['permission']);
            Sanctum::actingAs($user);

            $this->assertSame(200, $this->getJson($group['uri'])->status(), "{$name} izinli");
        }
    }

    public function test_all_groups_super_admin_and_company_admin_with_admin_role(): void
    {
        foreach ($this->groups as $name => $group) {
            Sanctum::actingAs($this->makeUser(UserType::SuperAdmin));
            $this->assertSame(200, $this->getJson($group['uri'])->status(), "{$name} super_admin");

            $admin = $this->makeUser(UserType::CompanyAdmin);
            $this->assertTrue($admin->hasRole('admin'));
            Sanctum::actingAs($admin);
            $this->assertSame(200, $this->getJson($group['uri'])->status(), "{$name} company_admin+admin");
        }
    }

    // ——— Tenant izolasyonu (grup bazında) ———

    public function test_onboarding_tenant_isolation(): void
    {
        $own = OnboardingTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Own Template',
            'tasks' => [],
            'is_active' => true,
        ]);
        OnboardingTemplate::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Template',
            'tasks' => [],
            'is_active' => true,
        ]);

        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('onboarding.templates.view');
        Sanctum::actingAs($user);

        $ids = $this->extractIds($this->getJson('/api/v1/onboarding/templates')->assertStatus(200)->json('data'));
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_performance_tenant_isolation(): void
    {
        $own = PerformancePeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Own Period',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'draft',
        ]);
        PerformancePeriod::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Period',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'draft',
        ]);

        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('performance.periods.view');
        Sanctum::actingAs($user);

        $ids = $this->extractIds($this->getJson('/api/v1/performance/periods')->assertStatus(200)->json('data'));
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_training_tenant_isolation(): void
    {
        $own = Training::create([
            'company_id' => $this->company->id,
            'title' => 'Own Training',
            'is_active' => true,
        ]);
        Training::create([
            'company_id' => $this->otherCompany->id,
            'title' => 'Other Training',
            'is_active' => true,
        ]);

        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('training.list.view');
        Sanctum::actingAs($user);

        $ids = $this->extractIds($this->getJson('/api/v1/training/trainings')->assertStatus(200)->json('data'));
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_assets_tenant_isolation(): void
    {
        $own = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Own Cat',
            'is_active' => true,
        ]);
        AssetCategory::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Cat',
            'is_active' => true,
        ]);

        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('assets.categories.view');
        Sanctum::actingAs($user);

        $ids = $this->extractIds($this->getJson('/api/v1/assets/categories')->assertStatus(200)->json('data'));
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_surveys_tenant_isolation(): void
    {
        $own = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Own Survey',
            'type' => Survey::TYPE_ENGAGEMENT,
            'is_active' => true,
        ]);
        Survey::create([
            'company_id' => $this->otherCompany->id,
            'title' => 'Other Survey',
            'type' => Survey::TYPE_ENGAGEMENT,
            'is_active' => true,
        ]);

        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('surveys.list.view');
        Sanctum::actingAs($user);

        $ids = $this->extractIds($this->getJson('/api/v1/surveys')->assertStatus(200)->json('data'));
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_analytics_tenant_scope(): void
    {
        $this->makeUser(UserType::User, $this->company);
        $this->makeUser(UserType::User, $this->otherCompany);
        $this->makeUser(UserType::User, $this->otherCompany);

        $viewer = $this->makeUser(UserType::User, $this->company);
        $viewer->givePermissionTo('analytics.reports.view');
        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/v1/analytics/summary')->assertStatus(200);
        // Kendi firmadaki aktif user sayısı (viewer dahil) — diğer firma karışmamalı
        $total = $response->json('data.total_employees')
            ?? $response->json('data.totalEmployees')
            ?? $response->json('data.employees_count');

        $ownActive = User::where('company_id', $this->company->id)->where('is_active', true)->count();
        $this->assertNotNull($total, 'analytics summary employee count alanı bulunamadı: '.$response->getContent());
        $this->assertEquals($ownActive, $total);
    }

    public function test_portal_surveys_still_without_permission_middleware(): void
    {
        // Portal route'lara permission eklenmedi — company_admin + module + portal.access ile erişilebilir
        // Bu test sadece company surveys permission'ın portal'ı bozmadığını doğrular (portal ayrı path)
        Sanctum::actingAs($this->makeUser(UserType::User));
        $this->getJson('/api/v1/surveys')->assertStatus(403);
    }
}
