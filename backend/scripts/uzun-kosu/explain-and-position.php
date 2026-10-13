<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cid = (int) DB::table('companies')->where('slug', 'scale-co-1')->value('id');
$out = [];

$sqls = [
    'employees_filter' => "EXPLAIN (ANALYZE, BUFFERS) SELECT id FROM employees WHERE company_id={$cid} AND deleted_at IS NULL AND status='active' AND position LIKE '%Yazılım%' ORDER BY id LIMIT 25",
    'attendance_month' => "EXPLAIN (ANALYZE, BUFFERS) SELECT date, count(*) FROM attendance_records WHERE company_id={$cid} AND date BETWEEN '2024-06-01' AND '2024-06-30' GROUP BY date",
    'leave_list' => "EXPLAIN (ANALYZE, BUFFERS) SELECT id FROM leave_requests WHERE company_id={$cid} AND deleted_at IS NULL ORDER BY id DESC LIMIT 25",
    'position_group' => "EXPLAIN (ANALYZE, BUFFERS) SELECT position, count(*) FROM employees WHERE company_id={$cid} AND deleted_at IS NULL GROUP BY position",
    'attendance_order_limit' => "EXPLAIN (ANALYZE, BUFFERS) SELECT id, user_id, date FROM attendance_records WHERE company_id={$cid} ORDER BY date DESC LIMIT 50",
];

foreach ($sqls as $k => $sql) {
    $rows = DB::select($sql);
    $text = implode("\n", array_map(fn ($r) => array_values((array) $r)[0], $rows));
    preg_match('/Execution Time: ([0-9.]+) ms/', $text, $m);
    $out[$k] = ['exec_ms' => isset($m[1]) ? (float) $m[1] : null, 'plan' => $text];
}

$out['position_group_rows'] = DB::table('employees')
    ->where('company_id', $cid)
    ->whereNull('deleted_at')
    ->select('position', DB::raw('count(*) as c'))
    ->groupBy('position')
    ->orderByDesc('c')
    ->get();

$mix = DB::select("
SELECT
  count(*) FILTER (WHERE EXISTS (
    SELECT 1 FROM positions p WHERE p.company_id=e.company_id AND p.deleted_at IS NULL AND p.code=e.position
  )) AS match_code,
  count(*) FILTER (WHERE NOT EXISTS (
    SELECT 1 FROM positions p WHERE p.company_id=e.company_id AND p.deleted_at IS NULL AND p.code=e.position
  ) AND EXISTS (
    SELECT 1 FROM positions p WHERE p.company_id=e.company_id AND p.deleted_at IS NULL AND p.name=e.position
  )) AS match_name_only,
  count(*) FILTER (WHERE e.position IS NOT NULL AND e.position<>'' AND NOT EXISTS (
    SELECT 1 FROM positions p WHERE p.company_id=e.company_id AND p.deleted_at IS NULL AND (p.code=e.position OR p.name=e.position)
  )) AS match_neither
FROM employees e WHERE e.company_id={$cid} AND e.deleted_at IS NULL
");
$out['scale_position_mix'] = $mix[0] ?? null;

// Org align for group scope
$ids = DB::table('companies')->where('slug', 'like', 'scale-co-%')->orderBy('id')->pluck('id');
$orgId = DB::table('organizations')->where('slug', 'scale-holding')->value('id');
if (! $orgId) {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'Scale Holding',
        'slug' => 'scale-holding',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
DB::table('companies')->whereIn('id', $ids)->update(['organization_id' => $orgId]);
$adminId = DB::table('users')->where('email', 'admin-scale-1@example.test')->value('id');
foreach ($ids as $i => $companyId) {
    DB::table('company_user')->updateOrInsert(
        ['user_id' => $adminId, 'company_id' => $companyId],
        ['is_default' => $i === 0, 'created_at' => now()]
    );
}
$out['scale_org_id'] = $orgId;
$out['scale_company_ids'] = $ids->values()->all();

file_put_contents(__DIR__.'/explain.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'exec_ms' => array_map(fn ($x) => $x['exec_ms'] ?? null, array_intersect_key($out, array_flip(array_keys($sqls)))),
    'mix' => $out['scale_position_mix'],
    'position_groups' => $out['position_group_rows'],
    'org_id' => $orgId,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
