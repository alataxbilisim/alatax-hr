<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Demo\DemoSentinel;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-4 — demo tek kaynak, D2c retention, kardeş firma izolasyonu, sentinel.
 */
class DemoDataSeederGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_thin_wrapper_over_demo_data_seeder(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::query()->where('email', DemoSentinel::ADMIN_EMAIL)->count());
        $this->assertSame(1, Company::query()->where('slug', DemoSentinel::COMPANY_SLUG)->count());
        $this->assertTrue(Company::query()->where('slug', 'demo-otel-b')->exists());
    }

    public function test_seeder_idempotent_no_duplicate_admin_or_company(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(1, User::query()->where('email', DemoSentinel::ADMIN_EMAIL)->count());
        $this->assertSame(1, Company::query()->where('slug', DemoSentinel::COMPANY_SLUG)->count());
        $this->assertSame(1, User::query()->where('email', 'admin@otel-b.demo.test')->count());
        $this->assertSame(1, Company::query()->where('slug', 'demo-otel-b')->count());
        $this->assertSame(1, Company::query()->where('slug', 'demo-otel-c')->count());
    }

    public function test_retention_policies_inactive_after_seed(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(
            0,
            RetentionPolicy::query()->where('active', true)->count(),
            'D2c: seed sonrası aktif retention_policy olmamalı'
        );

        $demo = RetentionPolicy::query()
            ->where('name', 'Demo Saklama Politikası')
            ->first();
        $this->assertNotNull($demo);
        $this->assertFalse((bool) $demo->active);
    }

    public function test_demo_sentinel_passes_after_seed(): void
    {
        $this->seed(DemoDataSeeder::class);

        $result = app(DemoSentinel::class)->inspect();
        $this->assertTrue($result['ok'], implode('; ', $result['failures']));
        $this->assertTrue($result['checks']['has_admin_role']);
        $this->assertGreaterThanOrEqual(50, $result['checks']['permission_count']);
        foreach (DemoSentinel::REQUIRED_PERMISSIONS as $perm) {
            $this->assertTrue(
                $result['checks']['permissions'][$perm] ?? false,
                "yetki eksik: {$perm}"
            );
        }
    }

    public function test_sister_company_tenant_isolation_on_employees_list(): void
    {
        $this->seed(DemoDataSeeder::class);

        $adminA = User::query()->where('email', DemoSentinel::ADMIN_EMAIL)->firstOrFail();
        $adminB = User::query()->where('email', 'admin@otel-b.demo.test')->firstOrFail();
        $companyB = Company::query()->where('slug', 'demo-otel-b')->firstOrFail();

        $bCodes = Employee::query()
            ->where('company_id', $companyB->id)
            ->pluck('employee_code')
            ->all();
        $this->assertNotEmpty($bCodes);
        $this->assertContains('OTB-001', $bCodes);

        Sanctum::actingAs($adminA->fresh());
        $listA = $this->getJson('/api/v1/employees?per_page=100')->assertOk()->json('data');
        $codesA = collect($listA)->pluck('employee_code')->filter()->values();
        foreach ($bCodes as $code) {
            $this->assertFalse(
                $codesA->contains($code),
                "Firma A listesinde kardeş kod görünmemeli: {$code}"
            );
        }
        $this->assertTrue($codesA->contains('DEM-001'));

        Sanctum::actingAs($adminB->fresh());
        $listB = $this->getJson('/api/v1/employees?per_page=100')->assertOk()->json('data');
        $codesB = collect($listB)->pluck('employee_code')->filter()->values();
        $this->assertTrue($codesB->contains('OTB-001'));
        $this->assertFalse($codesB->contains('DEM-001'));
        $this->assertFalse($codesB->contains('OTC-001'));
        $this->assertLessThanOrEqual(15, $codesB->count());
    }

    public function test_sister_company_employee_detail_cross_access_denied(): void
    {
        $this->seed(DemoDataSeeder::class);

        $adminA = User::query()->where('email', DemoSentinel::ADMIN_EMAIL)->firstOrFail();
        $empB = Employee::query()
            ->where('employee_code', 'OTB-002')
            ->firstOrFail();

        Sanctum::actingAs($adminA->fresh());
        $this->getJson('/api/v1/employees/'.$empB->id)
            ->assertStatus(404);
    }
}
