<?php

/**
 * PHP backed enum üretici — migration PortableEnum::column değerlerinden.
 * İsim: App\Enums\{StudlyFromColumn} + çakışmada Values hash suffix.
 */

$files = glob(__DIR__.'/../database/migrations/*.php') ?: [];
$sets = []; // valuesKey => ['values'=>[], 'usages'=>[]]

foreach ($files as $f) {
    $c = file_get_contents($f);
    if (! preg_match_all(
        '/PortableEnum::column\(\$table,\s*\'([^\']+)\'\s*,\s*\[(.*?)\]\s*,/s',
        $c,
        $m,
        PREG_SET_ORDER
    )) {
        continue;
    }
    foreach ($m as $match) {
        preg_match_all('/\'([^\']+)\'/', $match[2], $vm);
        $values = $vm[1];
        $key = implode('|', $values);
        $sets[$key]['values'] = $values;
        $sets[$key]['columns'][] = $match[1];
        $sets[$key]['files'][] = basename($f);
    }
}

$dir = __DIR__.'/../app/Enums';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$usedNames = [];
$generated = [];

foreach ($sets as $key => $info) {
    $column = $info['columns'][0];
    $base = str_replace(' ', '', ucwords(str_replace('_', ' ', $column)));
    // Prefer semantic names for known columns
    $map = [
        'type|super_admin' => 'UserType', // won't match
    ];
    $name = $base.'Enum';

    // Disambiguate identical column names with different values
    if (isset($usedNames[$name])) {
        $name = $base.substr(md5($key), 0, 6).'Enum';
    }
    // Better: if column status with different sets, use file hint
    $colCounts = array_count_values($info['columns']);
    if (($colCounts[$column] ?? 0) >= 1 && isset($usedNames[$name])) {
        $name = $base.substr(md5($key), 0, 8).'Enum';
    }
    while (isset($usedNames[$name])) {
        $name .= 'X';
    }
    $usedNames[$name] = true;

    $cases = '';
    foreach ($info['values'] as $v) {
        $caseName = str_replace(' ', '', ucwords(str_replace('_', ' ', $v)));
        // numeric-safe / reserved
        if (is_numeric($caseName[0] ?? '')) {
            $caseName = 'V'.$caseName;
        }
        if (in_array(strtolower($caseName), ['new', 'default', 'class', 'string', 'array', 'match', 'fn', 'parent', 'self'], true)) {
            $caseName = $caseName.'Value';
        }
        // New is reserved-ish as case New
        if ($v === 'new') {
            $caseName = 'New_';
        }
        $cases .= "    case {$caseName} = '{$v}';\n";
    }

    $php = <<<PHP
<?php

namespace App\Enums;

/**
 * Auto-generated (Faz 1). Values mirrored from migrations CHECK constraint.
 * Usages: {$info['columns'][0]} — {$info['files'][0]}
 */
enum {$name}: string
{
{$cases}}

PHP;

    file_put_contents("{$dir}/{$name}.php", $php);
    $generated[] = $name.' => '.implode(',', $info['values']);
}

file_put_contents(__DIR__.'/enum_map.json', json_encode($generated, JSON_PRETTY_PRINT));
echo 'GENERATED='.count($generated)."\n";
