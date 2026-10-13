<?php

declare(strict_types=1);

$lines = file(__DIR__.'/pdks-parallel-results.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$ok = $err = $dl = 0;
$lat = [];
$bad = 0;
foreach ($lines as $line) {
    $p = preg_split('/\s+/', trim($line)) ?: [];
    if (count($p) < 3) {
        $bad++;

        continue;
    }
    $lat[] = (int) $p[2];
    if ($p[1] === 'ok') {
        $ok++;
    } elseif ($p[1] === 'deadlock') {
        $dl++;
        $err++;
    } else {
        $err++;
    }
}
sort($lat);
$n = count($lat);
$pctl = fn (float $q) => $n ? $lat[(int) floor(($q / 100) * ($n - 1))] : 0;
echo json_encode([
    'lines' => count($lines),
    'bad' => $bad,
    'ok' => $ok,
    'err' => $err,
    'deadlock' => $dl,
    'p50' => $pctl(50),
    'p95' => $pctl(95),
    'p99' => $pctl(99),
    'sample' => array_slice($lines, 0, 5),
], JSON_PRETTY_PRINT)."\n";
