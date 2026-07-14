<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Module;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\DefaultCompanyHrSeedService;
use App\Services\DefaultLeaveApprovalWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * FAZ A6 — Lokal/demo veri (idempotent). Production'da çalışmaz.
 *
 * php artisan db:seed --class=DemoSeeder
 *
 * Hesaplar (şifre hepsi: Demo1234!):
 *   admin@demo.test      — admin (company_admin)
 *   hr@demo.test         — hr_manager
 *   sube-ist@demo.test   — branch_manager (İstanbul)
 *   sube-ank@demo.test   — branch_manager (Ankara)
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo1234!';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder production ortamında çalıştırılamaz.');
            Log::warning('DemoSeeder blocked in production');

            return;
        }

        $company = Company::firstOrCreate(
            ['slug' => 'demo-firma'],
            [
                'name' => 'Demo Firma AŞ',
                'status' => 'active',
                'package_type' => 'professional',
                'user_limit' => 100,
                'trial_ends_at' => now()->addYear(),
            ]
        );

        $sync = [];
        foreach (Module::all() as $module) {
            $sync[$module->id] = ['is_active' => true, 'activated_at' => now()];
        }
        $company->modules()->syncWithoutDetaching($sync);

        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($company);
        app(DefaultCompanyHrSeedService::class)->ensureForCompany($company);
        Holiday::seedTurkishHolidaysForYears([2026, 2027, 2028]);

        $hq = Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HQ'],
            ['name' => 'Merkez', 'city' => 'İstanbul', 'is_active' => true, 'is_headquarters' => true]
        );
        $ist = Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'IST'],
            ['name' => 'İstanbul', 'city' => 'İstanbul', 'is_active' => true, 'is_headquarters' => false]
        );
        $ank = Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'ANK'],
            ['name' => 'Ankara', 'city' => 'Ankara', 'is_active' => true, 'is_headquarters' => false]
        );

        $deptIk = Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'IK'],
            ['name' => 'İnsan Kaynakları', 'is_active' => true]
        );
        $deptIt = Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'IT'],
            ['name' => 'Bilgi Teknolojileri', 'is_active' => true]
        );
        $deptSat = Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SAT'],
            ['name' => 'Satış', 'is_active' => true]
        );
        $deptOpr = Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'OPR'],
            ['name' => 'Operasyon', 'is_active' => true]
        );

        $pos = fn (string $code, string $fallback) => Position::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->value('name') ?? $fallback;

        $admin = $this->upsertUser($company->id, 'admin@demo.test', 'Demo Admin', 'admin', UserType::CompanyAdmin);
        $hr = $this->upsertUser($company->id, 'hr@demo.test', 'Demo İK', 'hr_manager');
        $bmIst = $this->upsertUser($company->id, 'sube-ist@demo.test', 'İstanbul Şube Yöneticisi', 'branch_manager');
        $bmAnk = $this->upsertUser($company->id, 'sube-ank@demo.test', 'Ankara Şube Yöneticisi', 'branch_manager');

        $adminEmp = $this->upsertEmployee($company->id, 'DEM-001', $admin, $deptIk->id, $hq->id, 'Genel Müdür', $pos('GEN_MUD', 'Genel Müdür'));
        $hrEmp = $this->upsertEmployee($company->id, 'DEM-002', $hr, $deptIk->id, $hq->id, 'İK Müdürü', $pos('IK_MUD', 'İK Müdürü'), $adminEmp->id);
        $bmIstEmp = $this->upsertEmployee($company->id, 'DEM-003', $bmIst, $deptSat->id, $ist->id, 'Şube Yöneticisi', $pos('SAT_MUD', 'Satış Müdürü'), $adminEmp->id);
        $bmAnkEmp = $this->upsertEmployee($company->id, 'DEM-004', $bmAnk, $deptOpr->id, $ank->id, 'Şube Yöneticisi', $pos('ISLET_M', 'İşletme Müdürü'), $adminEmp->id);

        $staff = [
            ['DEM-005', 'Ayşe Demir', 'ayse.demir@demo.test', $deptIt->id, $hq->id, $pos('YAZ_GEL', 'Yazılım Geliştirici'), $adminEmp->id],
            ['DEM-006', 'Can Yıldız', 'can.yildiz@demo.test', $deptIt->id, $hq->id, $pos('YAZ_KID', 'Kıdemli Yazılım Geliştirici'), $adminEmp->id],
            ['DEM-007', 'Elif Kara', 'elif.kara@demo.test', $deptIk->id, $hq->id, $pos('IK_UZM', 'İK Uzmanı'), $hrEmp->id],
            ['DEM-008', 'Burak Şahin', 'burak.sahin@demo.test', $deptSat->id, $ist->id, $pos('SAT_TEM', 'Satış Temsilcisi'), $bmIstEmp->id],
            ['DEM-009', 'Zeynep Ak', 'zeynep.ak@demo.test', $deptSat->id, $ist->id, $pos('MUS_HIZ', 'Müşteri Hizmetleri'), $bmIstEmp->id],
            ['DEM-010', 'Mert Çelik', 'mert.celik@demo.test', $deptSat->id, $ist->id, $pos('SAT_TEM', 'Satış Temsilcisi'), $bmIstEmp->id],
            ['DEM-011', 'Selin Öztürk', 'selin.ozturk@demo.test', $deptOpr->id, $ank->id, $pos('LOJ_UZM', 'Lojistik Uzmanı'), $bmAnkEmp->id],
            ['DEM-012', 'Emre Aydın', 'emre.aydin@demo.test', $deptOpr->id, $ank->id, $pos('DEPO_SOR', 'Depo Sorumlusu'), $bmAnkEmp->id],
            ['DEM-013', 'Deniz Kılıç', 'deniz.kilic@demo.test', $deptOpr->id, $ank->id, $pos('IDARI_I', 'İdari İşler'), $bmAnkEmp->id],
            ['DEM-014', 'Gökhan Arslan', 'gokhan.arslan@demo.test', $deptIt->id, $ist->id, $pos('SIS_YON', 'Sistem Yöneticisi'), $bmIstEmp->id],
            ['DEM-015', 'İrem Koç', 'irem.koc@demo.test', $deptSat->id, $hq->id, $pos('PAZ_UZM', 'Pazarlama Uzmanı'), $hrEmp->id],
        ];

        foreach ($staff as [$code, $name, $email, $deptId, $branchId, $position, $managerId]) {
            $u = $this->upsertUser($company->id, $email, $name, 'employee');
            $this->upsertEmployee($company->id, $code, $u, $deptId, $branchId, $position, $position, $managerId);
        }

        $this->command?->info('DemoSeeder tamam (idempotent).');
        $this->command?->table(
            ['E-posta', 'Rol', 'Şifre'],
            [
                ['admin@demo.test', 'admin / company_admin', self::PASSWORD],
                ['hr@demo.test', 'hr_manager', self::PASSWORD],
                ['sube-ist@demo.test', 'branch_manager (İstanbul)', self::PASSWORD],
                ['sube-ank@demo.test', 'branch_manager (Ankara)', self::PASSWORD],
            ]
        );
    }

    private function upsertUser(int $companyId, string $email, string $name, string $roleName, UserType $type = UserType::User): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'company_id' => $companyId,
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'type' => $type,
                'is_active' => true,
                'must_change_password' => false,
                'preferences' => ['theme' => 'dark', 'locale' => 'tr'],
            ]
        );

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
        if ($roleName === 'admin' && $role->data_scope === null) {
            $role->forceFill(['data_scope' => 'company'])->save();
        }
        if ($roleName === 'branch_manager' && $role->data_scope === null) {
            $role->forceFill(['data_scope' => 'branch'])->save();
        }
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    private function upsertEmployee(
        int $companyId,
        string $code,
        User $user,
        int $departmentId,
        int $branchId,
        string $title,
        string $position,
        ?int $managerId = null,
    ): Employee {
        return Employee::updateOrCreate(
            ['company_id' => $companyId, 'employee_code' => $code],
            [
                'user_id' => $user->id,
                'department_id' => $departmentId,
                'branch_id' => $branchId,
                'title' => $title,
                'position' => $position,
                'manager_id' => $managerId,
                'hire_date' => '2022-01-15',
                'contract_type' => 'permanent',
                'work_type' => 'full_time',
                'status' => 'active',
                'personal_email' => $user->email,
                'currency' => 'TRY',
            ]
        );
    }
}
