<?php

namespace App\Console\Commands;

use App\Support\E2eDatabaseGuard;
use Database\Seeders\E2eSeeder;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Playwright öncesi E2E veritabanını sıfırlar: `migrate:fresh --seed --seeder=E2eSeeder`.
 * Yalnız adı `_e2e` ile biten veritabanında çalışır (docker compose `app-e2e` servisi).
 */
class E2eResetCommand extends Command
{
    protected $signature = 'e2e:reset';

    protected $description = 'E2E veritabanını (yalnız *_e2e) sıfırla ve E2eSeeder ile tohumla';

    public function handle(): int
    {
        try {
            E2eDatabaseGuard::assertE2eDatabase();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('E2E veritabanı sıfırlanıyor: '.E2eDatabaseGuard::currentDatabase());

        return $this->call('migrate:fresh', [
            '--seed' => true,
            '--seeder' => E2eSeeder::class,
            '--force' => true,
        ]);
    }
}
