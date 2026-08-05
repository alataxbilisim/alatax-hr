<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * gorsel:fixture artığı temizliği — şirket SİLMEZ.
 * Yalnız GORSEL-* / gorsel.fixture / gorsel-aday-* işaretli kayıtlar.
 */
class GorselFixtureCleanupCommand extends Command
{
    protected $signature = 'gorsel:fixture-cleanup
                            {--dry-run : Silmeden say}
                            {--force : Onaysız çalıştır}';

    protected $description = 'gorsel:fixture ile işaretlenmiş kayıtları temizle (şirket silmez)';

    /** Fixture’ın oluşturduğu / güncellediği e-postalar (şirket dışı kullanıcılar). */
    private const FIXTURE_USER_EMAILS = [
        'tek@demo.test',
        '2fa@demo.test',
        // portal@demo.test demo seed’de de olabilir — yalnız GORSEL-PORTAL employee ile bağ varsa dokunulmaz burada
    ];

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Yalnız local/testing.');

            return self::FAILURE;
        }

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->warn("Hedef DB: {$db} (connection=".config('database.default').')');

        if (! $this->option('force') && ! $this->option('dry-run')) {
            if (! $this->confirm('Fixture artıkları silinsin mi? (şirketler silinmez)', false)) {
                $this->info('İptal.');

                return self::SUCCESS;
            }
        }

        $dry = (bool) $this->option('dry-run');

        // CatalogNotification type = sınıf adı; eventKey data.event içinde
        $notif = DB::table('notifications')->where('data', 'like', '%gorsel.fixture%');
        $notifCount = (clone $notif)->count();
        if (! $dry && $notifCount > 0) {
            $notif->delete();
        }
        $this->line(($dry ? '[dry] ' : '')."notifications event=gorsel.fixture: {$notifCount}");

        $leaveQ = LeaveRequest::withoutGlobalScopes()->where('reason', 'gorsel-fixture');
        $leaveCount = (clone $leaveQ)->count();
        if (! $dry && $leaveCount > 0) {
            $leaveQ->delete();
        }
        $this->line(($dry ? '[dry] ' : '')."leave_requests reason=gorsel-fixture: {$leaveCount}");

        $docQ = Document::withoutGlobalScopes()->where('file_path', 'gorsel/fixture.pdf');
        $docCount = (clone $docQ)->count();
        if (! $dry && $docCount > 0) {
            $docQ->delete();
        }
        $this->line(($dry ? '[dry] ' : '').'documents gorsel/fixture.pdf: '.$docCount);

        $appQ = JobApplication::withoutGlobalScopes()->where('email', 'like', 'gorsel-aday-%@demo.test');
        $appCount = (clone $appQ)->count();
        if (! $dry && $appCount > 0) {
            $appQ->delete();
        }
        $this->line(($dry ? '[dry] ' : '').'job_applications gorsel-aday-*: '.$appCount);

        $empQ = Employee::withoutGlobalScopes()->where('employee_code', 'like', 'GORSEL-%');
        $empCount = (clone $empQ)->count();
        if (! $dry && $empCount > 0) {
            $empQ->delete();
        }
        $this->line(($dry ? '[dry] ' : '').'employees GORSEL-*: '.$empCount);

        $userEmails = self::FIXTURE_USER_EMAILS;
        $userCount = User::query()->whereIn('email', $userEmails)->count();
        if (! $dry && $userCount > 0) {
            User::query()->whereIn('email', $userEmails)->each(function (User $u): void {
                DB::table('company_user')->where('user_id', $u->id)->delete();
                $u->tokens()->delete();
                $u->delete();
            });
        }
        $this->line(($dry ? '[dry] ' : '').'users tek@/2fa@: '.$userCount);

        $this->info($dry
            ? 'Dry-run bitti — şirketlere dokunulmadı.'
            : 'Temizlik bitti — şirketlere dokunulmadı.');

        return self::SUCCESS;
    }
}
