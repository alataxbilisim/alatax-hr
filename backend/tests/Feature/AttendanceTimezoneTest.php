<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Timesheet\AttendanceClockService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Temel sağlamlaştırma — TR duvar saati / app.timezone=Europe/Istanbul.
 */
class AttendanceTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        config(['app.timezone' => 'Europe/Istanbul']);
        date_default_timezone_set('Europe/Istanbul');

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'employee_code' => 'TZ-EMP-1',
            'full_name' => 'TZ Personel',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_app_timezone_is_europe_istanbul(): void
    {
        $this->assertSame('Europe/Istanbul', config('app.timezone'));
    }

    public function test_punch_at_2330_tr_writes_same_tr_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 23:30:00', 'Europe/Istanbul'));

        $svc = app(AttendanceClockService::class);
        CompanyContext::run($this->company->id, function () use ($svc) {
            $svc->clockIn($this->user->fresh(), ['source' => AttendanceClockService::SOURCE_PORTAL]);
        });

        $rec = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($rec);
        $this->assertSame('2026-08-05', (string) $rec->getRawOriginal('date'));
        $this->assertStringStartsWith('23:30', (string) $rec->getRawOriginal('clock_in'));
    }

    public function test_punch_at_0030_tr_writes_new_tr_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 00:30:00', 'Europe/Istanbul'));

        $svc = app(AttendanceClockService::class);
        CompanyContext::run($this->company->id, function () use ($svc) {
            $svc->clockIn($this->user->fresh(), ['source' => AttendanceClockService::SOURCE_PORTAL]);
        });

        $rec = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($rec);
        $this->assertSame('2026-08-06', (string) $rec->getRawOriginal('date'));
        $this->assertStringStartsWith('00:30', (string) $rec->getRawOriginal('clock_in'));
    }

    public function test_monthly_bounds_use_tr_calendar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', 'Europe/Istanbul'));

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $this->assertSame('2026-08-01', $start);
        $this->assertSame('2026-08-31', $end);

        // TR gece yarısı sonrası hâlâ Ağustos ayında
        Carbon::setTestNow(Carbon::parse('2026-08-31 23:30:00', 'Europe/Istanbul'));
        $this->assertSame(8, (int) now()->month);
        $this->assertSame('2026-08-31', now()->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-09-01 00:30:00', 'Europe/Istanbul'));
        $this->assertSame(9, (int) now()->month);
        $this->assertSame('2026-09-01', now()->toDateString());
    }

    public function test_audit_instant_stable_across_display_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 23:30:00', 'Europe/Istanbul'));
        $instant = now()->clone()->utc()->toIso8601String();

        // Aynı anı UTC display ile karşılaştır
        $asUtc = Carbon::parse('2026-08-05 23:30:00', 'Europe/Istanbul')->utc()->toIso8601String();
        $this->assertSame($asUtc, $instant);
        $this->assertSame('2026-08-05T20:30:00+00:00', $asUtc);
    }
}
