<?php

/**
 * UZUN KOŞU adım 3 — sorgu sağlığı (dobedan_hr_testing + scale-co-* verisi).
 * docker exec -e DB_DATABASE=dobedan_hr_testing dobedan-hr-app php scripts/uzun-kosu/query-health.php
 */

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Services\Reports\DatasetRegistry;
use App\Services\Reports\ReportDefinitionService;
use Illuminate\Support\Facades\DB;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dbName = (string) config('database.connections.'.config('database.default').'.database');
if ($dbName !== 'dobedan_hr_testing') {
    fwrite(STDERR, "ABORT: need dobedan_hr_testing, got {$dbName}\n");
    exit(1);
}

$company = Company::query()->where('slug', 'scale-co-1')->first();
if (! $company) {
    fwrite(STDERR, "ABORT: scale-co-1 yok — önce seed-scale.php\n");
    exit(1);
}
$admin = User::query()->where('home_company_id', $company->id)->where('email', 'admin-scale-1@example.test')->first();
if (! $admin) {
    fwrite(STDERR, "ABORT: scale admin yok\n");
    exit(1);
}

$queries = [];
DB::listen(function ($q) use (&$queries): void {
    $queries[] = [
        'sql' => $q->sql,
        'time_ms' => $q->time,
        'bindings' => $q->bindings,
    ];
});

function runCase(string $name, callable $fn): array
{
    global $queries;
    $queries = [];
    $t0 = microtime(true);
    $fn();
    $wall = (microtime(true) - $t0) * 1000;
    $times = array_column($queries, 'time_ms');
    $slow = array_values(array_filter($queries, fn ($q) => $q['time_ms'] >= 300));
    usort($queries, fn ($a, $b) => $b['time_ms'] <=> $a['time_ms']);
    $top = $queries[0] ?? null;

    return [
        'case' => $name,
        'query_count' => count($queries),
        'wall_ms' => round($wall, 1),
        'sum_query_ms' => round(array_sum($times), 1),
        'max_query_ms' => $top ? round($top['time_ms'], 1) : 0,
        'max_sql' => $top ? substr(preg_replace('/\s+/', ' ', $top['sql']), 0, 240) : null,
        'slow_ge_300' => array_map(fn ($q) => [
            'ms' => round($q['time_ms'], 1),
            'sql' => substr(preg_replace('/\s+/', ' ', $q['sql']), 0, 200),
        ], $slow),
    ];
}

$results = [];
$cid = (int) $company->id;

$results[] = runCase('employees_list_unfiltered', function () use ($cid): void {
    DB::table('employees')->where('company_id', $cid)->whereNull('deleted_at')->orderBy('id')->limit(25)->get();
});

$results[] = runCase('employees_list_filtered_status_position', function () use ($cid): void {
    DB::table('employees')
        ->where('company_id', $cid)
        ->whereNull('deleted_at')
        ->where('status', 'active')
        ->where('position', 'like', '%Yazılım%')
        ->orderBy('id')
        ->limit(25)
        ->get();
});

$results[] = runCase('leave_requests_list', function () use ($cid): void {
    DB::table('leave_requests')
        ->where('company_id', $cid)
        ->whereNull('deleted_at')
        ->orderByDesc('id')
        ->limit(25)
        ->get();
});

$results[] = runCase('attendance_monthly_agg', function () use ($cid): void {
    DB::table('attendance_records')
        ->selectRaw('date, count(*) as cnt, sum(total_hours) as hours')
        ->where('company_id', $cid)
        ->whereBetween('date', ['2024-06-01', '2024-06-30'])
        ->groupBy('date')
        ->orderBy('date')
        ->get();
});

$registry = app(DatasetRegistry::class);
$reportSvc = app(ReportDefinitionService::class);
foreach (array_keys($registry->all()) as $key) {
    $results[] = runCase("dataset_{$key}_company", function () use ($registry, $reportSvc, $admin, $cid, $key): void {
        $ds = $registry->get($key);
        $fieldKeys = array_map(fn ($f) => (string) $f->key, $ds->fieldsForCompany($cid));
        $config = [
            'dataset' => $key,
            'fields' => array_slice($fieldKeys, 0, 5),
            'limit' => 50,
        ];
        try {
            $reportSvc->preview($admin, $cid, $config);
        } catch (Throwable $e) {
            echo "dataset {$key} err: ".$e->getMessage()."\n";
        }
    });
}

// EXPLAIN ANALYZE seçilmiş
$explains = [];
$explainSqls = [
    'employees_filter' => "EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) SELECT * FROM employees WHERE company_id = {$cid} AND deleted_at IS NULL AND status = 'active' AND position LIKE '%Yazılım%' ORDER BY id LIMIT 25",
    'attendance_month' => "EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) SELECT date, count(*) FROM attendance_records WHERE company_id = {$cid} AND date BETWEEN '2024-06-01' AND '2024-06-30' GROUP BY date ORDER BY date",
    'leave_list' => "EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) SELECT * FROM leave_requests WHERE company_id = {$cid} AND deleted_at IS NULL ORDER BY id DESC LIMIT 25",
    'position_group' => "EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) SELECT position, count(*) FROM employees WHERE company_id = {$cid} AND deleted_at IS NULL GROUP BY position ORDER BY count(*) DESC",
];
foreach ($explainSqls as $k => $sql) {
    $rows = DB::select($sql);
    $explains[$k] = array_map(fn ($r) => (array) $r, $rows);
}

$out = [
    'company_id' => $cid,
    'cases' => $results,
    'slow_all_ge_300' => array_values(array_filter($results, fn ($r) => $r['max_query_ms'] >= 300 || count($r['slow_ge_300']) > 0)),
    'explains' => $explains,
];
file_put_contents(__DIR__.'/query-health.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['cases' => $results, 'slow_count' => count($out['slow_all_ge_300'])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
