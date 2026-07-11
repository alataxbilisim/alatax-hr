<?php

$dir = __DIR__.'/../database/migrations';
foreach (glob($dir.'/*.php') as $path) {
    $c = file_get_contents($path);
    if (! str_contains($c, 'PortableEnum::column')) {
        continue;
    }

    // Tüm flushChecks satırlarını temizle
    $c = preg_replace('/[ \t]*\\\\App\\\\Support\\\\PortableEnum::flushChecks\(\);\r?\n/', '', $c);

    if (! preg_match('/public function up\(\)[^{]*\{/', $c, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('no up: '.basename($path));
    }

    $pos = $m[0][1] + strlen($m[0][0]);
    $depth = 1;
    $len = strlen($c);
    $end = null;

    for ($i = $pos; $i < $len; $i++) {
        $ch = $c[$i];
        if ($ch === "'" || $ch === '"') {
            $q = $ch;
            $i++;
            while ($i < $len && $c[$i] !== $q) {
                if ($c[$i] === '\\') {
                    $i++;
                }
                $i++;
            }
            continue;
        }
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $i;
                break;
            }
        }
    }

    if ($end === null) {
        throw new RuntimeException('up end not found: '.basename($path));
    }

    $c2 = substr($c, 0, $end)."        \\App\\Support\\PortableEnum::flushChecks();\n    ".substr($c, $end);
    file_put_contents($path, $c2);
    echo 'fixed '.basename($path)."\n";
}
echo "DONE\n";
