<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mevcut Postgres volume'lerinde test veritabanını oluşturur (boş volume'de
 * docker/postgres/init/01-test-db.sh yapar). İdempotent; yalnız `*_testing` adları.
 */
class CreateTestingDatabaseCommand extends Command
{
    protected $signature = 'db:create-testing {--database= : Test veritabanı adı (varsayılan: <DB_DATABASE>_testing)}';

    protected $description = 'Test veritabanını yoksa oluşturur (idempotent; yalnız *_testing adları)';

    private const MAINTENANCE_CONNECTION = 'pgsql_maintenance';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error('Yalnız PostgreSQL bağlantısı desteklenir.');

            return self::FAILURE;
        }

        $base = (string) ($config['database'] ?? '');
        $name = (string) ($this->option('database') ?: (str_ends_with($base, '_testing') ? $base : $base.'_testing'));

        if (preg_match('/^[a-z0-9_]+_testing$/', $name) !== 1) {
            $this->error("Geçersiz test veritabanı adı: [{$name}] (\"_testing\" ile bitmeli).");

            return self::FAILURE;
        }

        config(['database.connections.'.self::MAINTENANCE_CONNECTION => array_merge($config, ['database' => 'postgres'])]);

        try {
            $maintenance = DB::connection(self::MAINTENANCE_CONNECTION);

            if ($maintenance->table('pg_database')->where('datname', $name)->exists()) {
                $this->info("Test veritabanı zaten var: {$name}");

                return self::SUCCESS;
            }

            $maintenance->getSchemaBuilder()->createDatabase($name);
            $this->info("Test veritabanı oluşturuldu: {$name}");

            return self::SUCCESS;
        } finally {
            DB::purge(self::MAINTENANCE_CONNECTION);
        }
    }
}
