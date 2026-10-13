<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Reports\ReportDefinitionService;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = User::query()->where('email', 'admin@demo.test')->firstOrFail();
$cid = (int) $admin->home_company_id;
$svc = app(ReportDefinitionService::class);
$r = $svc->preview($admin, $cid, [
    'dataset' => 'employees',
    'fields' => ['employee_code'],
    'limit' => 20,
    'scope' => 'group',
]);
echo json_encode([
    'user' => $admin->email,
    'home_company_id' => $cid,
    'report_scope' => $r['meta']['report_scope'] ?? null,
    'company_ids' => $r['meta']['company_ids'] ?? null,
    'count' => $r['meta']['count'] ?? null,
], JSON_PRETTY_PRINT)."\n";
