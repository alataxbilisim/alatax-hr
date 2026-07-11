<?php

/**
 * Faz 1 Adım A — tek geçişli güvenli dönüştürücü.
 * json→jsonb, enum→PortableEnum::column, flushChecks up() sonuna.
 */

$dir = __DIR__.'/../database/migrations';
$changed = 0;

foreach (glob($dir.'/*.php') as $path) {
    $original = file_get_contents($path);
    $content = preg_replace('/->json\(/', '->jsonb(', $original);

    $enumCount = 0;
    $content = preg_replace_callback(
        '/\$table->enum\(\s*\'([^\']+)\'\s*,\s*\[(.*?)\]\s*\)((?:\s*->(?:nullable|default|after)\([^;]*\))*)/s',
        static function (array $m) use (&$enumCount): string {
            $enumCount++;
            $column = $m[1];
            preg_match_all('/\'([^\']+)\'/', $m[2], $vm);
            $values = $vm[1];
            if ($values === []) {
                throw new RuntimeException('empty enum '.$column.' in');
            }

            $chain = $m[3] ?? '';
            $nullable = str_contains($chain, '->nullable(');
            $default = 'null';
            if (preg_match('/->default\(([^)]+)\)/', $chain, $dm)) {
                $default = trim($dm[1]);
            }
            $after = 'null';
            if (preg_match('/->after\(([^)]+)\)/', $chain, $am)) {
                $after = trim($am[1]);
            }

            $valuesPhp = '['.implode(', ', array_map(
                static fn (string $v): string => "'".str_replace(["\\", "'"], ["\\\\", "\\'"], $v)."'",
                $values
            )).']';

            $nullablePhp = $nullable ? 'true' : 'false';

            return "\\App\\Support\\PortableEnum::column(\$table, '{$column}', {$valuesPhp}, {$default}, {$nullablePhp}, 64, {$after})";
        },
        $content
    );

    if ($content === null) {
        throw new RuntimeException('regex fail '.basename($path));
    }

    if ($content === $original) {
        continue;
    }

    if ($enumCount > 0) {
        // up() kapanış brace bul (string-aware)
        if (! preg_match('/public function up\(\)[^{]*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            throw new RuntimeException('no up '.basename($path));
        }
        $pos = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $len = strlen($content);
        $end = null;
        for ($i = $pos; $i < $len; $i++) {
            $ch = $content[$i];
            if ($ch === "'" || $ch === '"') {
                $q = $ch;
                $i++;
                while ($i < $len && $content[$i] !== $q) {
                    if ($content[$i] === '\\') {
                        $i++;
                    }
                    $i++;
                }
                continue;
            }
            // skip // comments
            if ($ch === '/' && ($i + 1) < $len && $content[$i + 1] === '/') {
                while ($i < $len && $content[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            // skip /* */ comments
            if ($ch === '/' && ($i + 1) < $len && $content[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && ! ($content[$i] === '*' && $content[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
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
            throw new RuntimeException('up end '.basename($path));
        }
        $content = substr($content, 0, $end)
            ."        \\App\\Support\\PortableEnum::flushChecks();\n    "
            .substr($content, $end);
    }

    file_put_contents($path, $content);
    $changed++;
    echo basename($path)." enums={$enumCount}\n";
}

echo "CHANGED={$changed}\n";
