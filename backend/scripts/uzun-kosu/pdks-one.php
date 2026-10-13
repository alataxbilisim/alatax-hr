<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Timesheet\AttendanceClockService;
use Illuminate\Support\Facades\DB;

$userId = (int) ($argv[1] ?? 0);
if ($userId <= 0) {
    fwrite(STDERR, "usage: pdks-one.php USER_ID\n");
    exit(2);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t0 = microtime(true);
$code = 'ok';
$msg = '';
try {
    $user = User::query()->findOrFail($userId);
    $branchId = (int) DB::table('branches')->where('company_id', $user->home_company_id)->value('id');
    app(AttendanceClockService::class)->punch($user, [
        'method' => 'qr',
        'source' => AttendanceClockService::SOURCE_QR,
        'branch_id' => $branchId,
        'ip' => '10.0.0.'.($userId % 250),
    ], (int) $user->home_company_id);
} catch (Throwable $e) {
    $code = 'err';
    $msg = str_replace(["\n", "\r"], ' ', $e->getMessage());
    if (str_contains(strtolower($msg), 'deadlock') || str_contains($msg, '40P01')) {
        $code = 'deadlock';
    }
}
$ms = (int) round((microtime(true) - $t0) * 1000);
echo "{$userId} {$code} {$ms} {$msg}\n";
