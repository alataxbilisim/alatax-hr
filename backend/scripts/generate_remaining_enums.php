<?php

/**
 * Kalan unique CHECK setleri için PHP enum üret (çekirdek Enums/ altındakiler hariç).
 */

$existingValues = [];
foreach (glob(__DIR__.'/../app/Enums/*.php') ?: [] as $f) {
    $c = file_get_contents($f);
    if (preg_match_all('/case\s+\w+\s*=\s*\'([^\']+)\'/', $c, $m)) {
        $key = implode('|', $m[1]);
        $existingValues[$key] = basename($f, '.php');
    }
}

$files = glob(__DIR__.'/../database/migrations/*.php') ?: [];
$sets = [];
foreach ($files as $f) {
    $c = file_get_contents($f);
    if (! preg_match_all('/PortableEnum::column\(\$table,\s*\'([^\']+)\'\s*,\s*\[(.*?)\]\s*,/s', $c, $m, PREG_SET_ORDER)) {
        continue;
    }
    foreach ($m as $match) {
        preg_match_all('/\'([^\']+)\'/', $match[2], $vm);
        $values = $vm[1];
        $key = implode('|', $values);
        if (isset($existingValues[$key])) {
            continue;
        }
        $sets[$key] = [
            'values' => $values,
            'column' => $match[1],
            'file' => basename($f),
        ];
    }
}

$dir = __DIR__.'/../app/Enums';
$used = array_flip(array_map('basename', glob($dir.'/*.php') ?: []));
$n = 0;

foreach ($sets as $key => $info) {
    $base = str_replace(' ', '', ucwords(str_replace('_', ' ', $info['column'])));
    $name = $base.'Value';
    $suffix = substr(md5($key), 0, 6);
    if (isset($used[$name.'.php'])) {
        $name = $base.$suffix;
    }
    $used[$name.'.php'] = true;

    $cases = '';
    foreach ($info['values'] as $v) {
        $caseName = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $v)));
        if ($caseName === '' || is_numeric($caseName[0])) {
            $caseName = 'V'.$caseName;
        }
        if (strcasecmp($caseName, 'New') === 0) {
            $caseName = 'NewItem';
        }
        if (in_array($caseName, ['Class', 'String', 'Array', 'Default', 'Match', 'Parent', 'Self', 'Fn'], true)) {
            $caseName .= 'Case';
        }
        $cases .= "    case {$caseName} = '{$v}';\n";
    }

    $php = <<<PHP
<?php

namespace App\\Enums;

/** Auto (Faz 1). Migration: {$info['file']}::{$info['column']} */
enum {$name}: string
{
{$cases}}

PHP;
    file_put_contents("{$dir}/{$name}.php", $php);
    $n++;
    echo "{$name}\n";
}
echo "GENERATED={$n}\n";
