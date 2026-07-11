<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Taşınabilir string + CHECK enum (mysql / pgsql). Native DB ENUM yok.
 * SQLite'ta CHECK atlanır; uygulama PHP enum cast ile doğrular.
 */
final class PortableEnum
{
    /** @var list<array{table: string, column: string, allowed: list<string>}> */
    private static array $pending = [];

    /**
     * @param  list<string>  $allowed
     */
    public static function column(
        Blueprint $table,
        string $column,
        array $allowed,
        ?string $default = null,
        bool $nullable = false,
        int $length = 64,
        ?string $after = null,
    ): void {
        $definition = $table->string($column, $length);

        if ($nullable) {
            $definition->nullable();
        }

        if ($default !== null) {
            $definition->default($default);
        }

        if ($after !== null) {
            $definition->after($after);
        }

        self::$pending[] = [
            'table' => $table->getTable(),
            'column' => $column,
            'allowed' => array_values($allowed),
        ];
    }

    public static function flushChecks(): void
    {
        foreach (self::$pending as $item) {
            self::addCheck($item['table'], $item['column'], $item['allowed']);
        }

        self::$pending = [];
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function addCheck(string $table, string $column, array $allowed): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $quoted = implode(', ', array_map(
            static fn (string $v): string => "'".str_replace("'", "''", $v)."'",
            $allowed
        ));
        $name = strtolower("{$table}_{$column}_check");

        if ($driver === 'pgsql') {
            $col = '"'.str_replace('"', '""', $column).'"';
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$col} IN ({$quoted}))");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $col = '`'.str_replace('`', '``', $column).'`';
            try {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$name}");
            } catch (\Throwable) {
                // yok
            }

            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$col} IN ({$quoted}))");
        }
    }
}
