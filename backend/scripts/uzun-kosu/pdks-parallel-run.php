<?php

/**
 * Gerçek çok süreçli punch — proc_open ile N worker.
 * docker exec -e DB_DATABASE=alatax_hr_testing alatax-hr-app php scripts/uzun-kosu/pdks-parallel-run.php
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$n = (int) ($argv[1] ?? 500);
$workers = (int) ($argv[2] ?? 40);
$cid = (int) DB::table('companies')->where('slug', 'scale-co-1')->value('id');
$uids = DB::table('users')
    ->where('company_id', $cid)
    ->where('email', 'like', 'scale-e%')
    ->orderBy('id')
    ->limit($n)
    ->pluck('id')
    ->all();

$today = now()->toDateString();
DB::table('attendance_records')->whereIn('user_id', $uids)->whereDate('date', $today)->delete();
file_put_contents(__DIR__.'/pdks-uids.json', json_encode(array_values($uids)));

$php = PHP_BINARY;
$script = __DIR__.'/pdks-one.php';
$resultsFile = __DIR__.'/pdks-parallel-results.txt';
file_put_contents($resultsFile, '');

$t0 = microtime(true);
$running = [];
$queue = $uids;
$ok = $err = $deadlock = 0;
$lat = [];

$launch = function (int $uid) use ($php, $script, &$running): void {
    $cmd = sprintf(
        'DB_DATABASE=alatax_hr_testing %s %s %d',
        escapeshellarg($php),
        escapeshellarg($script),
        $uid
    );
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__, 2));
    if (! is_resource($proc)) {
        return;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $running[$uid] = ['proc' => $proc, 'out' => $pipes[1], 'err' => $pipes[2], 'buf' => ''];
};

while ($queue !== [] || $running !== []) {
    while (count($running) < $workers && $queue !== []) {
        $uid = array_shift($queue);
        $launch((int) $uid);
    }
    foreach ($running as $uid => $r) {
        $r['buf'] .= stream_get_contents($r['out']) ?: '';
        stream_get_contents($r['err']);
        $status = proc_get_status($r['proc']);
        if (! $status['running']) {
            $r['buf'] .= stream_get_contents($r['out']) ?: '';
            fclose($r['out']);
            fclose($r['err']);
            proc_close($r['proc']);
            $line = trim($r['buf']);
            file_put_contents($resultsFile, $line.PHP_EOL, FILE_APPEND);
            $parts = preg_split('/\s+/', $line, 4) ?: [];
            if (count($parts) >= 3) {
                $code = $parts[1];
                $ms = (int) $parts[2];
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
            unset($running[$uid]);
        }
    }
    if ($running !== []) {
        usleep(2000);
    }
}

sort($lat);
$p = fn (float $q) => $lat === [] ? 0 : $lat[(int) floor(($q / 100) * (count($lat) - 1))];
$records = DB::table('attendance_records')->whereIn('user_id', $uids)->whereDate('date', $today)->count();
$dups = DB::table('attendance_records')
    ->select('user_id', DB::raw('count(*) c'))
    ->whereIn('user_id', $uids)
    ->whereDate('date', $today)
    ->groupBy('user_id')
    ->havingRaw('count(*) > 1')
    ->count();

$summary = [
    'mode' => 'proc_open_parallel',
    'workers' => $workers,
    'n' => count($uids),
    'ok' => $ok,
    'err' => $err,
    'deadlock' => $deadlock,
    'wall_ms' => round((microtime(true) - $t0) * 1000, 1),
    'p50_ms' => $p(50),
    'p95_ms' => $p(95),
    'p99_ms' => $p(99),
    'records_today' => $records,
    'dup_users' => $dups,
];
file_put_contents(__DIR__.'/pdks-parallel-summary.json', json_encode($summary, JSON_PRETTY_PRINT));
echo json_encode($summary, JSON_PRETTY_PRINT)."\n";
