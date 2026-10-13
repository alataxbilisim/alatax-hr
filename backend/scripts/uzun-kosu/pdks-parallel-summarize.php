<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$lines = file(__DIR__.'/pdks-parallel-results.txt', FILE_IGNORE_NEW_LINES) ?: [];
$ok = $err = $deadlock = 0;
$lat = [];
foreach ($lines as $line) {
    $p = preg_split('/\s+/', trim($line), 4);
    if (count($p) < 3) {
        continue;
    }
    $code = $p[1];
    $ms = (int) $p[2];
    $lat[] = $ms;
    if ($code === 'ok') {
        $ok++;
    } elseif ($code === 'deadlock') {
        $deadlock++;
        $err++;
    } else {
        $err++;
    }
}
sort($lat);
$pctl = function (float $q) use ($lat) {
    if ($lat === []) {
        return 0;
    }

    return $lat[(int) floor(($q / 100) * (count($lat) - 1))];
};

$uids = json_decode(file_get_contents(__DIR__.'/pdks-uids.json') ?: '[]', true) ?: [];
$today = now()->toDateString();
$records = Illuminate\Support\Facades\DB::table('attendance_records')
    ->whereIn('user_id', $uids)
    ->whereDate('date', $today)
    ->count();
$dups = Illuminate\Support\Facades\DB::table('attendance_records')
    ->select('user_id', Illuminate\Support\Facades\DB::raw('count(*) c'))
    ->whereIn('user_id', $uids)
    ->whereDate('date', $today)
    ->groupBy('user_id')
    ->havingRaw('count(*) > 1')
    ->count();

$summary = [
    'mode' => 'xargs_P_parallel_processes',
    'lines' => count($lines),
    'ok' => $ok,
    'err' => $err,
    'deadlock' => $deadlock,
    'p50_ms' => $pctl(50),
    'p95_ms' => $pctl(95),
    'p99_ms' => $pctl(99),
    'records_today' => $records,
    'dup_users' => $dups,
];
file_put_contents(__DIR__.'/pdks-parallel-summary.json', json_encode($summary, JSON_PRETTY_PRINT));
echo json_encode($summary, JSON_PRETTY_PRINT)."\n";
