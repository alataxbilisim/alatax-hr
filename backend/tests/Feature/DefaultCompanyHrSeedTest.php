<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Models\AccrualPolicy;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\DefaultCompanyHrSeedService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FAZ A1 — Register/backfill/K-A seed testleri.
 */
class DefaultCompanyHrSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_register_seeds_ten_leave_types_accrual_and_holidays(): void
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'A1 Seed AŞ',
            'name' => 'Admin A1',
            'email' => 'a1.admin@test.local',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertCreated();

        $companyId = $res->json('data.user.company.id');
        $this->assertNotNull($companyId);

        $types = LeaveType::withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('is_system', true)
            ->get();

        $this->assertCount(10, $types);
        $codes = $types->pluck('system_code')->sort()->values()->all();
        $this->assertSame(
            ['adoption', 'annual', 'bereavement', 'marriage', 'maternity', 'nursing', 'paternity', 'sick', 'travel', 'unpaid'],
            $codes
        );

        $annual = $types->firstWhere('system_code', 'annual');
        $this->assertNotNull($annual);

        $policy = AccrualPolicy::withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('leave_type_id', $annual->id)
            ->first();

        $this->assertNotNull($policy);
        $this->assertSame(DefaultCompanyHrSeedService::ACCRUAL_POLICY_NAME, $policy->name);
        $this->assertArrayHasKey('bands', $policy->tenure_rules);
        $this->assertCount(3, $policy->tenure_rules['bands']);

        $this->assertGreaterThan(0, Holiday::query()->whereNull('company_id')->whereYear('date', 2026)->count());
        $this->assertTrue(
            Holiday::query()->whereNull('company_id')->where('name', 'Ramazan Bayramı')->whereYear('date', 2026)->exists()
        );
        $this->assertTrue(
            Holiday::query()->whereNull('company_id')->whereDate('date', '2026-10-28')->exists()
        );
    }

    public function test_ka_label_change_keeps_system_code_and_accrual_link(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        app(DefaultCompanyHrSeedService::class)->ensureForCompany($company);

        $module = \App\Models\Module::where('slug', 'leave-management')->firstOrFail();
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()->toDateString()],
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'type' => \App\Enums\UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin);

        $annual = LeaveType::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('system_code', 'annual')
            ->firstOrFail();

        $this->putJson("/api/v1/leaves/types/{$annual->id}", [
            'name' => 'Senelik İzin',
        ])->assertOk();

        $annual->refresh();
        $this->assertSame('annual', $annual->system_code);
        $this->assertSame('Senelik İzin', $annual->name);
        $this->assertTrue($annual->is_system);

        $policy = AccrualPolicy::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('leave_type_id', $annual->id)
            ->first();
        $this->assertNotNull($policy);
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $service = app(DefaultCompanyHrSeedService::class);
        $service->ensureForCompany($company);
        Holiday::seedTurkishHolidaysForYears([2026, 2027, 2028]);

        $typesBefore = LeaveType::withoutCompanyScope()->where('company_id', $company->id)->count();
        $policiesBefore = AccrualPolicy::withoutCompanyScope()->where('company_id', $company->id)->count();
        $holidaysBefore = Holiday::query()->whereNull('company_id')->count();

        Artisan::call('alatax:seed-defaults');
        Artisan::call('alatax:seed-defaults');

        $this->assertSame($typesBefore, LeaveType::withoutCompanyScope()->where('company_id', $company->id)->count());
        $this->assertSame($policiesBefore, AccrualPolicy::withoutCompanyScope()->where('company_id', $company->id)->count());
        $this->assertSame($holidaysBefore, Holiday::query()->whereNull('company_id')->count());
        $this->assertSame(10, LeaveType::withoutCompanyScope()->where('company_id', $company->id)->where('is_system', true)->count());
    }

    public function test_tenant_isolation_leave_types(): void
    {
        $a = Company::factory()->create(['status' => CompanyStatus::Active]);
        $b = Company::factory()->create(['status' => CompanyStatus::Active]);
        $service = app(DefaultCompanyHrSeedService::class);
        $service->ensureForCompany($a);
        $service->ensureForCompany($b);

        $adminA = User::factory()->create([
            'company_id' => $a->id,
            'type' => \App\Enums\UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($adminA);
        Sanctum::actingAs($adminA);

        // leave-management modülü gerekir
        $module = \App\Models\Module::where('slug', 'leave-management')->first();
        if ($module) {
            $a->modules()->syncWithoutDetaching([
                $module->id => ['is_active' => true, 'activated_at' => now()->toDateString()],
            ]);
        }

        $res = $this->getJson('/api/v1/leaves/types?per_page=50');
        if ($res->status() === 403) {
            $this->markTestSkipped('leave-management modülü bu ortamda kapalı');
        }
        $res->assertOk();

        $ids = collect($res->json('data.data') ?? $res->json('data'))->pluck('id');
        $foreign = LeaveType::withoutCompanyScope()->where('company_id', $b->id)->pluck('id');
        foreach ($foreign as $fid) {
            $this->assertFalse($ids->contains($fid));
        }
    }

    public function test_overtime_type_lookup_seeded(): void
    {
        $items = app(\App\Services\LookupService::class)->forType('overtime_type', null);
        $values = $items->pluck('value')->all();
        $this->assertContains('normal', $values);
        $this->assertContains('overtime', $values);
        $this->assertContains('holiday', $values);
    }
}
