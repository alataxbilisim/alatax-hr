<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Services\Reports\ReportDefinitionService;
use Illuminate\Support\Facades\DB;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = Company::query()->where('slug', 'scale-co-1')->firstOrFail();
$admin = User::query()->where('home_company_id', $company->id)->where('email', 'admin-scale-1@example.test')->firstOrFail();
$cid = (int) $company->id;
$svc = app(ReportDefinitionService::class);

$cases = [
    'employees_preview_50' => function () use ($svc, $admin, $cid) {
        return $svc->preview($admin, $cid, [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'status', 'position'],
            'limit' => 50,
        ]);
    },
    'attendance_monthly_sql' => function () use ($cid) {
        return DB::table('attendance_records')
            ->selectRaw('date, count(*) as c, sum(total_hours) as h')
            ->where('company_id', $cid)
            ->whereBetween('date', ['2024-01-01', '2024-01-31'])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->count();
    },
    'export_employees_5k' => function () use ($svc, $admin, $cid) {
        $t0 = microtime(true);
        $r = $svc->export($admin, $cid, [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'full_name', 'position', 'status'],
            'limit' => 5000,
        ]);
        $json = json_encode($r['rows'] ?? []);

        return [
            'rows' => count($r['rows'] ?? []),
            'json_bytes' => strlen((string) $json),
            'elapsed_ms' => round((microtime(true) - $t0) * 1000, 1),
        ];
    },
    'position_group_raw_sql' => function () use ($cid) {
        return DB::table('employees')
            ->where('company_id', $cid)
            ->whereNull('deleted_at')
            ->select('position', DB::raw('count(*) as c'))
            ->groupBy('position')
            ->orderByDesc('c')
            ->get()
            ->count();
    },
];

$series = [];
foreach ($cases as $name => $fn) {
    $mem = [];
    $times = [];
    $lastMeta = null;
    for ($i = 0; $i < 50; $i++) {
        gc_collect_cycles();
        $t0 = microtime(true);
        $last = $fn();
        $times[] = (microtime(true) - $t0) * 1000;
        $mem[] = memory_get_usage(true);
        if ($i === 49) {
            $lastMeta = is_array($last) ? $last : ['scalar' => $last];
        }
        unset($last);
    }
    sort($times);
    $series[$name] = [
        'mem_first_mb' => round($mem[0] / 1048576, 2),
        'mem_last_mb' => round($mem[49] / 1048576, 2),
        'mem_max_mb' => round(max($mem) / 1048576, 2),
        'mem_slope_mb' => round(($mem[49] - $mem[0]) / 1048576, 3),
        'time_p50_ms' => round($times[24], 1),
        'time_avg_ms' => round(array_sum($times) / 50, 1),
        'time_max_ms' => round(max($times), 1),
        'last' => $lastMeta,
    ];
}

file_put_contents(__DIR__.'/memory-summary.json', json_encode($series, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($series, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
