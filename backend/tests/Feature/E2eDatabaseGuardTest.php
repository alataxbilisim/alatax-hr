<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Support\E2eDatabaseGuard;
use Database\Seeders\E2eSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Adım 03B C — E2E sıfırlaması yalnız `*_e2e` veritabanına karşı çalışır; canlı/test DB'si asla düşürülmez.
 */
class E2eDatabaseGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_e2e_database_by_suffix(): void
    {
        $this->assertTrue(E2eDatabaseGuard::isE2eDatabase('dobedan_hr_e2e'));
        $this->assertFalse(E2eDatabaseGuard::isE2eDatabase('dobedan_hr'));
        $this->assertFalse(E2eDatabaseGuard::isE2eDatabase('dobedan_hr_e2e_backup'));
        $this->assertFalse(E2eDatabaseGuard::isE2eDatabase(E2eDatabaseGuard::currentDatabase()));
    }

    public function test_reset_command_refuses_non_e2e_database_and_keeps_data(): void
    {
        $company = Company::factory()->create();

        $this->artisan('e2e:reset')
            ->expectsOutputToContain("'_e2e' ile bitmiyor")
            ->assertFailed();

        $this->assertModelExists($company);
    }

    public function test_direct_migrate_fresh_with_e2e_seeder_is_blocked_before_dropping_tables(): void
    {
        $company = Company::factory()->create();
        $this->routeConsoleEventsLikeCli();

        $error = null;
        try {
            Artisan::call('migrate:fresh', ['--seed' => true, '--seeder' => E2eSeeder::class, '--force' => true]);
        } catch (RuntimeException $e) {
            $error = $e;
        }

        $this->assertNotNull($error, 'migrate:fresh --seeder=E2eSeeder non-e2e veritabanında reddedilmeliydi.');
        $this->assertStringContainsString('_e2e', $error->getMessage());
        $this->assertModelExists($company);
    }

    public function test_direct_db_seed_with_e2e_seeder_is_blocked(): void
    {
        $this->routeConsoleEventsLikeCli();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('_e2e');

        Artisan::call('db:seed', ['--class' => 'E2eSeeder', '--force' => true]);
    }

    public function test_unrelated_commands_are_not_blocked(): void
    {
        $this->routeConsoleEventsLikeCli();

        $this->assertSame(0, Artisan::call('about', ['--only' => 'environment']));
    }

    /**
     * Laravel, CommandStarting olayını birim testlerinde bilerek yayınlamaz; gerçek `php artisan`
     * davranışını burada açıyoruz (yeni Artisan örneği Symfony dispatcher'ı ile kurulur).
     */
    private function routeConsoleEventsLikeCli(): void
    {
        $kernel = $this->app->make(ConsoleKernelContract::class);
        $this->assertInstanceOf(ConsoleKernel::class, $kernel);
        $kernel->rerouteSymfonyCommandEvents();
        $kernel->setArtisan(null);
    }
}
