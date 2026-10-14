<?php

/**
 * Larastan yalnız MySQL sözdizimli şema dump'ını okuyabiliyor; PostgreSQL dump'ı
 * (database/schema/pgsql-schema.sql) sessizce atlanıyor ve model kolonları bilinmiyor.
 * Bu betik bağlı veritabanının şemasını salt okunur inceleyip Larastan'a özel,
 * MySQL sözdizimli bir anlık görüntü üretir (git dışı). Kullanım: composer analyse.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = storage_path('framework/cache/phpstan-schema/schema.sql');

$mysqlType = static function (array $column): string {
    $full = $column['type'];

    return match ($column['type_name']) {
        'int2' => 'smallint',
        'int4' => 'int',
        'int8' => 'bigint',
        'bool' => 'boolean',
        'varchar', 'bpchar' => preg_match('/\((\d+)\)/', $full, $m) ? "varchar({$m[1]})" : 'varchar(255)',
        'json', 'jsonb' => 'json',
        'timestamp', 'timestamptz' => 'timestamp',
        'date' => 'date',
        'time', 'timetz' => 'time',
        'numeric' => preg_match('/\((\d+),\s*(\d+)\)/', $full, $m) ? "decimal({$m[1]},{$m[2]})" : 'decimal(20,6)',
        'float4' => 'float',
        'float8' => 'double',
        'uuid' => 'char(36)',
        'bytea' => 'blob',
        default => 'text',
    };
};

$statements = [];
foreach (Schema::getTables() as $table) {
    if (($table['schema'] ?? 'public') !== 'public') {
        continue;
    }

    $columns = array_map(
        static fn (array $c): string => sprintf('  `%s` %s%s', $c['name'], $mysqlType($c), $c['nullable'] ? ' NULL' : ' NOT NULL'),
        Schema::getColumns($table['name'])
    );

    $statements[$table['name']] = "CREATE TABLE `{$table['name']}` (\n".implode(",\n", $columns)."\n);";
}
ksort($statements);

if (! is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}
file_put_contents($target, "-- Larastan için üretildi (scripts/larastan-schema.php); elle düzenlemeyin.\n\n".implode("\n\n", $statements)."\n");

fwrite(STDOUT, count($statements)." tablo -> {$target}\n");
