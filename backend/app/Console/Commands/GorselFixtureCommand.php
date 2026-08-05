<?php

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Document;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\LeaveRequest;
use App\Models\Lookup;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CatalogNotification;
use App\Services\TwoFactorService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Görsel kontrol otomasyonu için idempotent demo fixture.
 * Yalnız local; demo-firma / demo-otel-b / demo-otel-c yoksa çıkar.
 */
class GorselFixtureCommand extends Command
{
    protected $signature = 'gorsel:fixture
                            {--secrets-dir= : 2FA secret yazılacak klasör (docs/gorsel-kontrol/<tarih>)}
                            {--allow-dev-db : Geliştirme DB (alatax_hr) üzerine yazmaya izin ver}
                            {--use-testing-db : Varsayılan yerine alatax_hr_testing bağlantısına yaz}';

    protected $description = 'Görsel kontrol fixture (idempotent, yalnız local)';

    private const REQUIRED_SLUGS = ['demo-firma', 'demo-otel-b', 'demo-otel-c'];

    private const PASSWORD = DemoDataSeeder::PASSWORD;

    public function handle(TwoFactorService $twoFactor): int
    {
        if (! app()->environment('local')) {
            $this->error('gorsel:fixture yalnız local ortamda çalışır (şu an: '.app()->environment().').');

            return self::FAILURE;
        }

        if ($this->option('use-testing-db')) {
            config(['database.default' => 'pgsql']);
            config(['database.connections.pgsql.database' => 'alatax_hr_testing']);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            $this->warn('Bağlantı: alatax_hr_testing (--use-testing-db)');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $this->info("gorsel:fixture hedef DB: {$database} (connection={$connection})");

        $isTestingDb = str_ends_with($database, '_testing') || $database === 'alatax_hr_testing';
        if (! $isTestingDb && ! $this->option('allow-dev-db')) {
            $this->error(
                "Geliştirme DB'sine yazmak için --allow-dev-db veya --use-testing-db kullanın. "
                .'Temizlik: php artisan gorsel:fixture-cleanup'
            );

            return self::FAILURE;
        }

        // Holding org hizası (şirket oluşturmaz; organization_id düzeltir)
        $align = app(\App\Services\Demo\DemoOrganizationAligner::class)->align();
        if ($align['ok']) {
            $this->line('OK demo org hiza: '.$align['message']);
        } else {
            $this->warn('demo org hiza atlandı: '.$align['message']);
        }

        $companies = Company::query()->whereIn('slug', self::REQUIRED_SLUGS)->get()->keyBy('slug');
        foreach (self::REQUIRED_SLUGS as $slug) {
            if (! $companies->has($slug)) {
                $this->error("Gerekli şirket yok: {$slug}. Önce demo:seed çalıştırın. Başka DB'ye dokunulmaz.");

                return self::FAILURE;
            }
        }

        /** @var Company $demoFirma */
        $demoFirma = $companies->get('demo-firma');
        /** @var Company $otelB */
        $otelB = $companies->get('demo-otel-b');
        /** @var Company $otelC */
        $otelC = $companies->get('demo-otel-c');

        $admin = User::query()->where('email', 'admin@demo.test')->first();
        if (! $admin) {
            $this->error('admin@demo.test yok — demo:seed gerekli.');

            return self::FAILURE;
        }

        // 1) admin → 3 şirket membership
        foreach ([$demoFirma, $otelB, $otelC] as $company) {
            $this->ensureMembership($admin, (int) $company->id, isDefault: $company->id === $demoFirma->id);
        }
        $this->line('OK admin@demo.test membership ×3');

        // 2) tek@demo.test → yalnız demo-otel-b
        $tek = $this->upsertUser(
            (int) $otelB->id,
            'tek@demo.test',
            'Görsel Tek Şirket',
            'admin',
            UserType::CompanyAdmin,
        );
        CompanyUser::query()->where('user_id', $tek->id)->where('company_id', '!=', $otelB->id)->delete();
        $this->ensureMembership($tek, (int) $otelB->id, isDefault: true);
        $tek->forceFill([
            'home_company_id' => $otelB->id,
            'last_company_id' => $otelB->id,
        ])->saveQuietly();
        $this->line('OK tek@demo.test → yalnız demo-otel-b');

        // 3) other_company_unread: admin için demo-otel-b'de okunmamış bildirim
        $existingOther = $admin->unreadNotifications()
            ->where('company_id', $otelB->id)
            ->count();
        if ($existingOther < 1) {
            $admin->notify(new CatalogNotification(
                'gorsel.fixture',
                [
                    'title' => 'Görsel kontrol bildirimi',
                    'message' => 'demo-otel-b okunmamış (other_company_unread)',
                    'link' => '/dashboard',
                ],
                (int) $otelB->id,
            ));
        }
        $this->line('OK other_company_unread fixture (otel-b)');

        // 4) portal@demo.test — yalnız employee rolü + Employee
        $portal = $this->upsertUser(
            (int) $demoFirma->id,
            'portal@demo.test',
            'Görsel Portal Personel',
            'employee',
            UserType::User,
        );
        $portal->syncRoles([Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'sanctum'])]);
        CompanyUser::query()->where('user_id', $portal->id)->where('company_id', '!=', $demoFirma->id)->delete();
        $this->ensureMembership($portal, (int) $demoFirma->id, isDefault: true);
        $portal->forceFill([
            'home_company_id' => $demoFirma->id,
            'last_company_id' => $demoFirma->id,
            'type' => UserType::User,
        ])->saveQuietly();

        $emp = Employee::withoutGlobalScopes()
            ->where('company_id', $demoFirma->id)
            ->where('user_id', $portal->id)
            ->first();
        if (! $emp) {
            Employee::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $demoFirma->id, 'employee_code' => 'GORSEL-PORTAL'],
                [
                    'user_id' => $portal->id,
                    'hire_date' => now()->subYear()->toDateString(),
                    'status' => 'active',
                    'created_by' => $admin->id,
                ]
            );
        }
        $this->line('OK portal@demo.test (employee-only + Employee)');

        // 5) 2fa@demo.test — TOTP aktif, secret dosyaya
        // Docker'da yalnız backend mount'lu → storage altına yaz; host runner docs'a kopyalar.
        $secretsDir = $this->option('secrets-dir')
            ?: storage_path('app/gorsel-kontrol');
        if (! is_dir($secretsDir)) {
            mkdir($secretsDir, 0755, true);
        }
        $secretPath = rtrim($secretsDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.secrets.local';

        $twoFa = $this->upsertUser(
            (int) $demoFirma->id,
            '2fa@demo.test',
            'Görsel 2FA Kullanıcı',
            'admin',
            UserType::CompanyAdmin,
        );
        $this->ensureMembership($twoFa, (int) $demoFirma->id, isDefault: true);

        $plainSecret = null;
        if (is_file($secretPath)) {
            $parsed = @parse_ini_file($secretPath) ?: [];
            $plainSecret = $parsed['TOTP_SECRET'] ?? null;
        }
        if (! is_string($plainSecret) || strlen($plainSecret) < 16) {
            $plainSecret = $twoFactor->generateSecret();
        }
        $recovery = $twoFactor->generateRecoveryCodes();
        $twoFa->forceFill([
            'home_company_id' => $demoFirma->id,
            'last_company_id' => $demoFirma->id,
            'two_factor_enabled' => true,
            'two_factor_secret' => $twoFactor->encryptSecret($plainSecret),
            'two_factor_recovery_codes' => $twoFactor->storeEncryptedRecoveryHashes(
                $twoFactor->hashRecoveryCodes($recovery)
            ),
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
            'must_change_password' => false,
        ])->saveQuietly();

        file_put_contents(
            $secretPath,
            "TOTP_SECRET={$plainSecret}\nTOTP_EMAIL=2fa@demo.test\nPASSWORD=".self::PASSWORD."\n"
        );
        $this->line("OK 2fa@demo.test — secret: {$secretPath}");

        // 6) Her şirkette ≥1 personel / izin / doküman / başvuru
        foreach ([$demoFirma, $otelB, $otelC] as $company) {
            $this->ensureCompanyContent($company, $admin);
        }
        $this->line('OK şirket içerik (personel/izin/doküman/başvuru)');

        // 7) En az bir lookup "kullanımda" (Employee.status referansı)
        $statusLookup = Lookup::query()
            ->where('lookup_type', 'employee_status')
            ->where(function ($q) use ($demoFirma) {
                $q->whereNull('company_id')->orWhere('company_id', $demoFirma->id);
            })
            ->where('value', 'active')
            ->first();
        if ($statusLookup) {
            $inUse = Employee::withoutGlobalScopes()
                ->where('company_id', $demoFirma->id)
                ->where('status', 'active')
                ->exists();
            $this->line($inUse
                ? 'OK lookup employee_status=active kullanımda'
                : 'WARN active employee yok — lookup kullanımda kanıtı zayıf');
        } else {
            $this->warn('employee_status/active lookup bulunamadı');
        }

        $this->info('gorsel:fixture tamam (idempotent).');

        return self::SUCCESS;
    }

    private function ensureMembership(User $user, int $companyId, bool $isDefault = false): void
    {
        $row = CompanyUser::query()->firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $companyId],
            ['role_id' => null, 'is_default' => $isDefault, 'created_at' => now()]
        );
        if ($isDefault && ! $row->is_default) {
            CompanyUser::query()->where('user_id', $user->id)->update(['is_default' => false]);
            $row->forceFill(['is_default' => true])->save();
        }
    }

    private function upsertUser(
        int $companyId,
        string $email,
        string $name,
        string $roleName,
        UserType $type,
    ): User {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'home_company_id' => $companyId,
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'type' => $type,
                'is_active' => true,
                'must_change_password' => false,
                'preferences' => ['theme' => 'light', 'locale' => 'tr', 'density' => 'comfortable'],
            ]
        );
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
        if (Schema::hasColumn('roles', 'data_scope') && $role->data_scope === null) {
            $role->forceFill(['data_scope' => 'company'])->save();
        }
        $user->syncRoles([$role]);

        return $user->fresh() ?? $user;
    }

    private function ensureCompanyContent(Company $company, User $actor): void
    {
        $cid = (int) $company->id;

        if (Employee::withoutGlobalScopes()->where('company_id', $cid)->count() < 1) {
            Employee::withoutGlobalScopes()->create([
                'company_id' => $cid,
                'employee_code' => 'GORSEL-'.Str::upper(Str::substr($company->slug, -4)).'-1',
                'hire_date' => now()->subMonths(3)->toDateString(),
                'status' => 'active',
                'created_by' => $actor->id,
            ]);
        }

        $employee = Employee::withoutGlobalScopes()
            ->where('company_id', $cid)
            ->whereNotNull('user_id')
            ->first()
            ?? Employee::withoutGlobalScopes()->where('company_id', $cid)->first();

        if ($employee && LeaveRequest::withoutGlobalScopes()->where('company_id', $cid)->count() < 1) {
            $leaveTypeId = DB::table('leave_types')->where('company_id', $cid)->value('id')
                ?? DB::table('leave_types')->value('id');
            $userId = $employee->user_id ?? $actor->id;
            if ($leaveTypeId && $userId) {
                LeaveRequest::withoutGlobalScopes()->create([
                    'company_id' => $cid,
                    'user_id' => $userId,
                    'leave_type_id' => $leaveTypeId,
                    'start_date' => now()->addDays(7)->toDateString(),
                    'end_date' => now()->addDays(9)->toDateString(),
                    'total_days' => 3,
                    'status' => 'pending',
                    'reason' => 'gorsel-fixture',
                    'created_by' => $actor->id,
                ]);
            }
        }

        if (Document::withoutGlobalScopes()->where('company_id', $cid)->count() < 1) {
            Document::withoutGlobalScopes()->create([
                'company_id' => $cid,
                'name' => 'Görsel Fixture Doküman',
                'file_path' => 'gorsel/fixture.pdf',
                'file_name' => 'fixture.pdf',
                'file_type' => 'application/pdf',
                'file_size' => 1024,
                'uploaded_by' => $actor->id,
            ]);
        }

        if (JobApplication::withoutGlobalScopes()->where('company_id', $cid)->count() < 1) {
            $jobId = DB::table('job_positions')->where('company_id', $cid)->value('id');
            if ($jobId) {
                JobApplication::withoutGlobalScopes()->create([
                    'company_id' => $cid,
                    'job_position_id' => $jobId,
                    'first_name' => 'Görsel',
                    'last_name' => 'Aday',
                    'email' => 'gorsel-aday-'.$company->slug.'@demo.test',
                    'status' => 'new',
                    'source' => 'manual',
                    'consent_kvkk' => true,
                    'consent_at' => now(),
                ]);
            }
        }
    }
}
