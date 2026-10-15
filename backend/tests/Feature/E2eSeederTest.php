<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\User;
use Database\Seeders\E2eSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Adım 03B C — Playwright senaryolarının dayandığı sabit veri seti (e2e/fixtures/data.ts ile aynı).
 */
class E2eSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_deterministic_tenants_users_and_leave_balance(): void
    {
        $this->seed(E2eSeeder::class);

        $companyA = Company::query()->where('slug', E2eSeeder::COMPANY_A_SLUG)->firstOrFail();
        $companyB = Company::query()->where('slug', E2eSeeder::COMPANY_B_SLUG)->firstOrFail();

        $superAdmin = User::query()->where('email', 'superadmin@e2e.test')->firstOrFail();
        $this->assertSame(UserType::SuperAdmin, $superAdmin->type);
        $this->assertTrue(Hash::check(E2eSeeder::PASSWORD, $superAdmin->password));

        $adminA = User::query()->where('email', 'admin@a.e2e.test')->firstOrFail();
        $this->assertSame($companyA->id, $adminA->home_company_id);
        $this->assertTrue($adminA->hasRole('admin'));

        $employeeA = User::query()->where('email', 'personel@a.e2e.test')->firstOrFail();
        $this->assertTrue($employeeA->hasRole('employee'));

        $twoFactor = User::query()->where('email', '2fa@a.e2e.test')->firstOrFail();
        $this->assertTrue($twoFactor->two_factor_enabled);
        $this->assertSame(E2eSeeder::TOTP_SECRET, decrypt($twoFactor->two_factor_secret));

        $employeeRecord = Employee::withoutGlobalScopes()->where('user_id', $employeeA->id)->firstOrFail();
        $adminRecord = Employee::withoutGlobalScopes()->where('user_id', $adminA->id)->firstOrFail();
        $this->assertSame($adminRecord->id, $employeeRecord->manager_id, 'İzin onayı dinamik yöneticiye gider.');

        $balance = LeaveBalance::withoutGlobalScopes()->where('user_id', $employeeA->id)->where('year', (int) now()->year)->firstOrFail();
        $this->assertEquals(E2eSeeder::ANNUAL_LEAVE_DAYS, $balance->total_days);
        $this->assertEquals(0, $balance->used_days);

        $hidden = Employee::withoutGlobalScopes()->where('employee_code', 'E2E-B-002')->firstOrFail();
        $this->assertSame($companyB->id, $hidden->company_id);
        $this->assertSame('B Gizli Personel', $hidden->full_name);
    }

    public function test_is_idempotent(): void
    {
        $this->seed(E2eSeeder::class);
        $counts = [Company::query()->count(), User::query()->count(), Employee::withoutGlobalScopes()->count(), LeaveBalance::withoutGlobalScopes()->count()];

        $this->seed(E2eSeeder::class);

        $this->assertSame($counts, [Company::query()->count(), User::query()->count(), Employee::withoutGlobalScopes()->count(), LeaveBalance::withoutGlobalScopes()->count()]);
    }
}
