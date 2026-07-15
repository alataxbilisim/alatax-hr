<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Lookup;
use App\Models\Module;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Services\LookupService;
use App\Services\Onboarding\DefaultOffboardingTemplateService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Offboarding Z1: process_type + SGK lookup + varsayılan şablon.
 */
class OffboardingTemplateSeedTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $mod = Module::firstOrCreate(
            ['slug' => 'onboarding'],
            ['name' => 'Onboarding', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $mod->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->admin->givePermissionTo([
            'onboarding.templates.view',
            'onboarding.templates.create',
        ]);
    }

    public function test_termination_reason_lookup_seeded(): void
    {
        $count = Lookup::query()
            ->where('lookup_type', LookupService::TYPE_TERMINATION_REASON)
            ->whereNull('company_id')
            ->count();

        $this->assertGreaterThanOrEqual(12, $count);
        $this->assertTrue(
            Lookup::query()
                ->where('lookup_type', LookupService::TYPE_TERMINATION_REASON)
                ->where('value', '03')
                ->exists()
        );
    }

    public function test_default_offboarding_template_seed_idempotent(): void
    {
        $svc = app(DefaultOffboardingTemplateService::class);
        $a = $svc->ensureForCompany($this->company);
        $b = $svc->ensureForCompany($this->company);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(OnboardingTemplate::TYPE_OFFBOARDING, $a->process_type);
        $this->assertCount(5, $a->tasks);
        $this->assertTrue($a->is_default);
    }

    public function test_template_list_filters_by_process_type(): void
    {
        OnboardingTemplate::create([
            'company_id' => $this->company->id,
            'process_type' => OnboardingTemplate::TYPE_ONBOARDING,
            'name' => 'Giriş',
            'tasks' => [['title' => 'T', 'type' => 'custom', 'is_required' => true]],
            'estimated_days' => 3,
            'is_active' => true,
            'is_default' => true,
        ]);
        app(DefaultOffboardingTemplateService::class)->ensureForCompany($this->company);

        Sanctum::actingAs($this->admin);

        $on = $this->getJson('/api/v1/onboarding/templates?process_type=onboarding')->assertOk();
        $off = $this->getJson('/api/v1/onboarding/templates?process_type=offboarding')->assertOk();

        $onNames = collect($on->json('data.data') ?? $on->json('data'))->pluck('name')->all();
        $offNames = collect($off->json('data.data') ?? $off->json('data'))->pluck('name')->all();

        $this->assertContains('Giriş', $onNames);
        $this->assertNotContains(DefaultOffboardingTemplateService::TEMPLATE_NAME, $onNames);
        $this->assertContains(DefaultOffboardingTemplateService::TEMPLATE_NAME, $offNames);
    }

    public function test_create_template_with_process_type_offboarding(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/onboarding/templates', [
            'name' => 'Özel Çıkış',
            'process_type' => 'offboarding',
            'tasks' => [
                [
                    'title' => 'Zimmet',
                    'type' => 'custom',
                    'is_required' => true,
                    'action_key' => 'asset_return',
                ],
            ],
            'estimated_days' => 5,
            'is_active' => true,
            'is_default' => false,
        ])->assertStatus(201)
            ->assertJsonPath('data.process_type', 'offboarding');
    }
}
