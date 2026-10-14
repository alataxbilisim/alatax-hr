<?php

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Yalnız PostgreSQL 16: başka sürücü bağlantısı ve MySQL'e özgü SQL geri gelmesin.
 */
class DatabaseDriverGuardTest extends TestCase
{
    private const SCANNED_DIRECTORIES = ['app', 'config', 'database', 'routes'];

    /** Büyük/küçük harf duyarsız aranır. */
    private const FORBIDDEN_DRIVER_WORDS = ['mysql', 'mariadb', 'sqlite'];

    /** Büyük/küçük harf duyarsız aranır. */
    private const FORBIDDEN_SQL_TOKENS = ['TIMESTAMPDIFF', 'CURDATE(', 'DATE_FORMAT('];

    public function test_runtime_config_has_only_pgsql_connection(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame(['pgsql'], array_keys(config('database.connections')));
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
    }

    public function test_database_config_file_defines_only_pgsql_connection(): void
    {
        $config = require base_path('config/database.php');

        $this->assertSame(['pgsql'], array_keys($config['connections']));
    }

    public function test_source_has_no_forbidden_driver_or_sql_tokens(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($lines as $index => $line) {
                foreach ([...self::FORBIDDEN_DRIVER_WORDS, ...self::FORBIDDEN_SQL_TOKENS] as $token) {
                    if (stripos($line, $token) !== false) {
                        $violations[] = sprintf(
                            '%s:%d [%s] %s',
                            str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1)),
                            $index + 1,
                            $token,
                            trim($line),
                        );
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Yasaklı veritabanı sürücüsü / MySQL SQL bulundu:\n".implode("\n", $violations));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(): iterable
    {
        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    yield $file;
                }
            }
        }
    }
}
