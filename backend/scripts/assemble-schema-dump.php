<?php

/**
 * Postgres 16 pg_dump çıktısını Laravel schema load için birleştir.
 * App konteynerindeki psql 15 \\restrict anlamaz → satırlar çıkarılır.
 */

$schemaDir = dirname(__DIR__).'/database/schema';
$schemaOnly = $schemaDir.'/_schema_only.sql';
$migData = $schemaDir.'/_mig_data.sql';
$out = $schemaDir.'/pgsql-schema.sql';

foreach ([$schemaOnly, $migData] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "Missing: {$f}\n");
        exit(1);
    }
}

$strip = static function (string $sql): string {
    $lines = preg_split("/\r\n|\n|\r/", $sql) ?: [];
    $kept = [];
    foreach ($lines as $line) {
        if (str_starts_with($line, '\\restrict ') || str_starts_with($line, '\\unrestrict ')) {
            continue;
        }
        $kept[] = $line;
    }

    return implode("\n", $kept);
};

$combined = $strip((string) file_get_contents($schemaOnly))."\n".$strip((string) file_get_contents($migData));
if (! str_ends_with($combined, "\n")) {
    $combined .= "\n";
}

file_put_contents($out, $combined);

$tzWith = substr_count($combined, 'with time zone');
$tzWithout = substr_count($combined, 'without time zone');
preg_match_all('/^\d+\t([^\t]+)\t\d+$/m', $combined, $m);
$migrationNames = $m[1] ?? [];

echo 'SCHEMA_BYTES='.strlen($combined).PHP_EOL;
echo 'TZ_WITH='.$tzWith.PHP_EOL;
echo 'TZ_WITHOUT='.$tzWithout.PHP_EOL;
echo 'MIGRATIONS='.count($migrationNames).PHP_EOL;

// prune
$migDir = dirname(__DIR__).'/database/migrations';
$deleted = 0;
foreach ($migrationNames as $name) {
    $path = $migDir.'/'.$name.'.php';
    if (is_file($path)) {
        unlink($path);
        $deleted++;
    }
}
$remaining = glob($migDir.'/*.php') ?: [];
echo 'PRUNED='.$deleted.PHP_EOL;
echo 'REMAINING='.count($remaining).PHP_EOL;
foreach ($remaining as $f) {
    echo 'LEFT:'.basename($f).PHP_EOL;
}

@unlink($schemaOnly);
@unlink($migData);

echo "OK\n";
