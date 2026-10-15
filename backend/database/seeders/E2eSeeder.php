<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\DefaultCompanyHrSeedService;
use App\Services\DefaultLeaveApprovalWorkflowService;
use App\Services\TwoFactorService;
use App\Support\E2eDatabaseGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Playwright E2E veri seti — deterministik, yalnız `*_e2e` veritabanı (`php artisan e2e:reset`).
 *
 * Sabit değerler `e2e/fixtures/data.ts` ile aynıdır; birini değiştiren ikisini de günceller.
 * Şifre ve TOTP anahtarı yalnız bu izole test veritabanına özgüdür.
 */
class E2eSeeder extends Seeder
{
    public const PASSWORD = 'E2e-Parola-2026!';

    /** RFC 4648 base32, yalnız E2E 2FA kullanıcısı için. */
    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public const COMPANY_A_SLUG = 'e2e-sirket-a';

    public const COMPANY_B_SLUG = 'e2e-sirket-b';

    public const ANNUAL_LEAVE_DAYS = 14;

    public function run(): void
    {
        if (! app()->runningUnitTests()) {
            E2eDatabaseGuard::assertE2eDatabase();
        }

        $this->call([
            ModuleSeeder::class,
            LicensePackageSeeder::class,
            PermissionSeeder::class,
            SystemReportPackageSeeder::class,
            LookupSeeder::class,
            EmployeeFormFieldSeeder::class,
        ]);

        User::updateOrCreate(['email' => 'superadmin@e2e.test'], [
            'name' => 'E2E Süper Yönetici',
            'password' => Hash::make(self::PASSWORD),
            'type' => UserType::SuperAdmin,
            'home_company_id' => null,
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        $companyA = $this->seedCompany(self::COMPANY_A_SLUG, 'E2E Şirket A');
        $adminA = $this->upsertUser($companyA, 'admin@a.e2e.test', 'A Yöneticisi', 'admin', UserType::CompanyAdmin);
        $employeeA = $this->upsertUser($companyA, 'personel@a.e2e.test', 'A Personeli', 'employee', UserType::User);
        $twoFactorUser = $this->upsertUser($companyA, '2fa@a.e2e.test', 'A İki Adımlı', 'admin', UserType::CompanyAdmin);
        $this->enableTwoFactor($twoFactorUser);

        $adminAEmployee = $this->upsertEmployee($companyA, 'E2E-A-001', 'A Yöneticisi', $adminA);
        $this->upsertEmployee($companyA, 'E2E-A-002', 'A Personeli', $employeeA, $adminAEmployee->id);
        $this->grantAnnualLeave($companyA, $employeeA);

        $companyB = $this->seedCompany(self::COMPANY_B_SLUG, 'E2E Şirket B');
        $adminB = $this->upsertUser($companyB, 'admin@b.e2e.test', 'B Yöneticisi', 'admin', UserType::CompanyAdmin);
        $adminBEmployee = $this->upsertEmployee($companyB, 'E2E-B-001', 'B Yöneticisi', $adminB);
        $this->upsertEmployee($companyB, 'E2E-B-002', 'B Gizli Personel', null, $adminBEmployee->id);
    }

    private function seedCompany(string $slug, string $name): Company
    {
        $company = Company::updateOrCreate(['slug' => $slug], [
            'name' => $name,
            'status' => 'active',
            'package_type' => 'professional',
            'user_limit' => 50,
            'trial_ends_at' => now()->addYear(),
        ]);

        $modules = [];
        foreach (Module::query()->where('is_active', true)->orderBy('id')->get() as $module) {
            $modules[$module->id] = ['is_active' => true, 'activated_at' => now()];
        }
        $company->modules()->syncWithoutDetaching($modules);

        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($company);
        app(DefaultCompanyHrSeedService::class)->ensureForCompany($company);

        Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'MRK'],
            ['name' => 'Merkez', 'city' => 'İstanbul', 'is_active' => true, 'is_headquarters' => true]
        );
        Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'IK'],
            ['name' => 'İnsan Kaynakları', 'is_active' => true]
        );
        Position::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'E2E_UZMAN'],
            ['name' => 'E2E Uzmanı', 'is_active' => true, 'sort_order' => 1]
        );

        return $company->fresh();
    }

    private function upsertUser(Company $company, string $email, string $name, string $roleName, UserType $type): User
    {
        $user = User::updateOrCreate(['email' => $email], [
            'home_company_id' => $company->id,
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'type' => $type,
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
            'preferences' => ['theme' => 'light', 'locale' => 'tr'],
        ]);

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'sanctum')->first();
        if ($role === null) {
            throw new RuntimeException("E2eSeeder: '{$roleName}' rolü yok (PermissionSeeder çalışmadı mı?).");
        }
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    private function upsertEmployee(Company $company, string $code, string $fullName, ?User $user, ?int $managerId = null): Employee
    {
        return Employee::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'employee_code' => $code],
            [
                'full_name' => $fullName,
                'user_id' => $user?->id,
                'branch_id' => Branch::withoutGlobalScopes()->where('company_id', $company->id)->where('code', 'MRK')->value('id'),
                'department_id' => Department::withoutGlobalScopes()->where('company_id', $company->id)->where('code', 'IK')->value('id'),
                'position_id' => Position::withoutGlobalScopes()->where('company_id', $company->id)->where('code', 'E2E_UZMAN')->value('id'),
                'title' => 'E2E Uzmanı',
                'manager_id' => $managerId,
                'hire_date' => '2024-01-15',
                'contract_type' => 'permanent',
                'work_type' => 'full_time',
                'status' => 'active',
                'personal_email' => $user?->email,
                'currency' => 'TRY',
            ]
        );
    }

    private function grantAnnualLeave(Company $company, User $user): void
    {
        $annual = LeaveType::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('system_code', 'annual')
            ->firstOrFail();

        LeaveBalance::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id, 'leave_type_id' => $annual->id, 'year' => (int) now()->year],
            ['total_days' => self::ANNUAL_LEAVE_DAYS, 'used_days' => 0, 'pending_days' => 0, 'carried_over' => 0]
        );
    }

    private function enableTwoFactor(User $user): void
    {
        $twoFactor = app(TwoFactorService::class);
        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $twoFactor->encryptSecret(self::TOTP_SECRET),
            'two_factor_recovery_codes' => $twoFactor->storeEncryptedRecoveryHashes(
                $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes())
            ),
        ])->saveQuietly();
    }
}
