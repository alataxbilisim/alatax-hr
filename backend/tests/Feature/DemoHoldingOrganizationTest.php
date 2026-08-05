<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\Demo\DemoOrganizationAligner;
use App\Services\GroupScopeService;
use Database\Seeders\DemoDataSeeder;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Demo holding bütünlüğü — üç demo şirket tek organization; merkez İK membership;
 * scope=group üç şirketi birden döndürür. Bozulursa demo ana senaryoyu temsil etmez.
 */
class DemoHoldingOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_puts_three_companies_under_one_organization(): void
    {
        $this->seed(DemoDataSeeder::class);

        $companies = Company::query()
            ->whereIn('slug', DemoOrganizationAligner::DEMO_COMPANY_SLUGS)
            ->get()
            ->keyBy('slug');

        foreach (DemoOrganizationAligner::DEMO_COMPANY_SLUGS as $slug) {
            $this->assertTrue($companies->has($slug), "eksik şirket: {$slug}");
        }

        $orgIds = $companies->pluck('organization_id')->map(fn ($id) => (int) $id)->unique()->values();
        $this->assertCount(1, $orgIds, 'Üç demo şirket aynı organization_id altında olmalı');
        $this->assertNotSame(0, $orgIds->first());

        $holding = \App\Models\Organization::query()
            ->where('slug', DemoOrganizationAligner::HOLDING_ORG_SLUG)
            ->first();
        $this->assertNotNull($holding);
        $this->assertSame((int) $holding->id, $orgIds->first());
    }

    public function test_admin_demo_is_member_of_all_three_and_group_scope_returns_them(): void
    {
        $this->seed(DemoDataSeeder::class);

        $admin = User::query()->where('email', 'admin@demo.test')->first();
        $this->assertNotNull($admin);

        $ids = Company::query()
            ->whereIn('slug', DemoOrganizationAligner::DEMO_COMPANY_SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $this->assertCount(3, $ids);

        foreach ($ids as $companyId) {
            $this->assertTrue(
                CompanyUser::query()
                    ->where('user_id', $admin->id)
                    ->where('company_id', $companyId)
                    ->exists(),
                "admin@demo.test membership eksik: company_id={$companyId}"
            );
        }

        $mainId = (int) Company::query()->where('slug', 'demo-firma')->value('id');
        $reportable = app(GroupScopeService::class)->reportableCompanyIds($admin, $mainId);
        sort($reportable);

        $this->assertSame(
            $ids,
            $reportable,
            'scope=group / reportableCompanyIds üç demo şirketi döndürmeli'
        );

        // İzin: reports.scope.group resolveForReport yolu
        $this->assertTrue($admin->can('reports.scope.group'));
        $resolved = app(GroupScopeService::class)->resolveForReport($admin, $mainId, 'group');
        sort($resolved);
        $this->assertSame($ids, $resolved);
    }

    public function test_align_command_is_idempotent_on_scattered_orgs(): void
    {
        $this->seed(DemoDataSeeder::class);

        $companies = Company::query()
            ->whereIn('slug', DemoOrganizationAligner::DEMO_COMPANY_SLUGS)
            ->get();

        // G1 benzeri dağınıklık simüle et
        foreach ($companies as $i => $company) {
            $org = \App\Models\Organization::query()->create([
                'name' => 'Scatter '.$company->slug,
                'slug' => 'org-scatter-'.$company->slug.'-'.$i,
            ]);
            $company->forceFill(['organization_id' => $org->id])->saveQuietly();
        }

        $orgIdsBefore = Company::query()
            ->whereIn('slug', DemoOrganizationAligner::DEMO_COMPANY_SLUGS)
            ->pluck('organization_id')
            ->unique();
        $this->assertCount(3, $orgIdsBefore);

        $this->artisan('demo:align-organizations', ['--with-memberships' => true])
            ->assertSuccessful();

        $after = Company::query()
            ->whereIn('slug', DemoOrganizationAligner::DEMO_COMPANY_SLUGS)
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $this->assertCount(1, $after);

        $this->artisan('demo:align-organizations')->assertSuccessful();
        $again = Company::query()
            ->whereIn('slug', DemoOrganizationAligner::DEMO_COMPANY_SLUGS)
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $this->assertSame($after->all(), $again->all());
    }
}
