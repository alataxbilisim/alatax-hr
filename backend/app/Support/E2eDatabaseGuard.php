<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Uçtan uca (Playwright) test veritabanı koruması.
 *
 * E2E sıfırlaması `migrate:fresh` ile tüm tabloları düşürür; bu yalnız adı `_e2e` ile biten
 * veritabanına (ör. `dobedan_hr_e2e`) karşı çalışabilir. Canlı geliştirme DB'si asla hedef olamaz.
 */
final class E2eDatabaseGuard
{
    public const SUFFIX = '_e2e';

    public static function currentDatabase(): string
    {
        $connection = (string) config('database.default');

        return (string) config("database.connections.{$connection}.database");
    }

    public static function isE2eDatabase(?string $database = null): bool
    {
        return str_ends_with($database ?? self::currentDatabase(), self::SUFFIX);
    }

    /**
     * @throws RuntimeException
     */
    public static function assertE2eDatabase(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('E2E veritabanı işlemleri production ortamında çalıştırılamaz.');
        }

        $database = self::currentDatabase();
        if (! self::isE2eDatabase($database)) {
            throw new RuntimeException(
                "E2E işlemi reddedildi: veritabanı adı '".self::SUFFIX."' ile bitmiyor ({$database})."
            );
        }
    }

    /** `migrate:fresh --seeder=E2eSeeder` / `db:seed --class=E2eSeeder` çağrısı mı? */
    public static function targetsE2eSeeder(?string $command, InputInterface $input): bool
    {
        if (! in_array($command, ['migrate:fresh', 'migrate:refresh', 'db:seed'], true)) {
            return false;
        }

        $seeder = $input->getParameterOption(['--seeder', '--class'], null, true);

        return is_string($seeder) && str_ends_with(ltrim($seeder, '\\'), 'E2eSeeder');
    }
}
