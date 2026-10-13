<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Reports\ReportDefinitionService;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = User::query()->where('email', 'admin-scale-1@example.test')->firstOrFail();
$cid = (int) $admin->home_company_id;
$svc = app(ReportDefinitionService::class);
$q = 0;
DB::listen(function () use (&$q): void {
    $q++;
});
$t0 = microtime(true);
try {
    $r = $svc->preview($admin, $cid, [
        'dataset' => 'employees',
        'fields' => ['employee_code', 'position'],
        'limit' => 50,
        'scope' => 'group',
    ]);
    echo json_encode([
        'ok' => true,
        'wall_ms' => round((microtime(true) - $t0) * 1000, 1),
        'queries' => $q,
        'count' => $r['meta']['count'] ?? null,
        'company_ids' => $r['meta']['company_ids'] ?? null,
        'report_scope' => $r['meta']['report_scope'] ?? null,
    ], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT)."\n";
}
