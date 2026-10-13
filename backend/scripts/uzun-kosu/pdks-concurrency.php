<?php

/**
 * UZUN KOŞU adım 4 — PDKS eşzamanlılık (service-level, alatax_hr_testing).
 * 500 farklı personel aynı anda punch; + aynı personel 5× ardışık.
 *
 * docker exec -e DB_DATABASE=alatax_hr_testing alatax-hr-app php scripts/uzun-kosu/pdks-concurrency.php
 */

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\User;
use App\Services\Timesheet\AttendanceClockService;
use Illuminate\Support\Facades\DB;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dbName = (string) config('database.connections.'.config('database.default').'.database');
if ($dbName !== 'alatax_hr_testing') {
    fwrite(STDERR, "ABORT: need testing DB\n");
    exit(1);
}

$company = Company::query()->where('slug', 'scale-co-1')->firstOrFail();
$branchId = (int) DB::table('branches')->where('company_id', $company->id)->value('id');
$users = User::query()
    ->where('company_id', $company->id)
    ->where('email', 'like', 'scale-e%')
    ->orderBy('id')
    ->limit(500)
    ->get();

if ($users->count() < 500) {
    fwrite(STDERR, 'ABORT: need 500 scale users, got '.$users->count()."\n");
    exit(1);
}

// Bugünkü kayıtları temizle (ölçüm için)
$today = now()->toDateString();
$userIds = $users->pluck('id')->all();
DB::table('attendance_records')
    ->whereIn('user_id', $userIds)
    ->whereDate('date', $today)
    ->delete();

$clock = app(AttendanceClockService::class);
$latencies = [];
$ok = 0;
$err = 0;
$errors = [];
$deadlocks = 0;

$t0 = microtime(true);
// PHP tek süreç — gerçek paralel için pcntl yok; simülasyon: ardışık hızlı çağrı + ayrı fork denemesi
// Not: Docker PHP'de paralel için curl multi ile HTTP daha doğru. Burada service-level burst.
foreach ($users as $u) {
    $s = microtime(true);
    try {
        $clock->punch($u, [
            'method' => 'qr',
            'source' => AttendanceClockService::SOURCE_QR,
            'branch_id' => $branchId,
            'ip' => '10.0.0.1',
            'device_info' => 'uzun-kosu-bench',
        ], (int) $company->id);
        $ok++;
    } catch (Throwable $e) {
        $err++;
        $msg = $e->getMessage();
        if (str_contains(strtolower($msg), 'deadlock') || str_contains($msg, '40P01')) {
            $deadlocks++;
        }
        $errors[] = substr($msg, 0, 120);
    }
    $latencies[] = (microtime(true) - $s) * 1000;
}
$wall = (microtime(true) - $t0) * 1000;

sort($latencies);
$p = fn (float $q) => $latencies[(int) floor(($q / 100) * (count($latencies) - 1))] ?? 0;

$recordsToday = AttendanceRecord::query()
    ->whereIn('user_id', $userIds)
    ->whereDate('date', $today)
    ->count();

$dupCheck = DB::table('attendance_records')
    ->select('user_id', DB::raw('count(*) as c'))
    ->whereIn('user_id', $userIds)
    ->whereDate('date', $today)
    ->groupBy('user_id')
    ->havingRaw('count(*) > 1')
    ->count();

// Aynı personel 5×
$one = $users->first();
DB::table('attendance_records')->where('user_id', $one->id)->whereDate('date', $today)->delete();
$fiveResults = [];
for ($i = 0; $i < 5; $i++) {
    try {
        $r = $clock->punch($one, [
            'method' => 'qr',
            'source' => AttendanceClockService::SOURCE_QR,
            'branch_id' => $branchId,
        ], (int) $company->id);
        $fiveResults[] = ['ok' => true, 'action' => $r['action']];
    } catch (Throwable $e) {
        $fiveResults[] = ['ok' => false, 'error' => $e->getMessage()];
    }
}
$oneCount = AttendanceRecord::query()->where('user_id', $one->id)->whereDate('date', $today)->count();

// HTTP paralel (500 curl) — opsiyonel ikinci faz: token gerekir; burada service ölçümü birincil
$summary = [
    'mode' => 'service_burst_sequential_sim',
    'note' => 'PHP tek süreç; gerçek 500 paralel HTTP için pdks-http-parallel.sh kullanılır',
    'n' => 500,
    'ok' => $ok,
    'err' => $err,
    'deadlocks' => $deadlocks,
    'wall_ms' => round($wall, 1),
    'p50_ms' => round($p(50), 2),
    'p95_ms' => round($p(95), 2),
    'p99_ms' => round($p(99), 2),
    'records_today' => $recordsToday,
    'users_with_dup_rows' => $dupCheck,
    'expected_behavior_same_user_5x' => '1. punch=clock_in, 2.=clock_out, 3+=exception (zaten çıkış) → tek satır',
    'same_user_5x_results' => $fiveResults,
    'same_user_record_count' => $oneCount,
    'sample_errors' => array_slice(array_unique($errors), 0, 10),
];
file_put_contents(__DIR__.'/pdks-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
