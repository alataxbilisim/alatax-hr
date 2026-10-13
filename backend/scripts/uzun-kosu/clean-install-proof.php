<?php

/**
 * Adım 9 — temiz kurulum provası (yalnız alatax_hr_testing).
 * NOT: migrate:fresh çalıştırır — scale verisini siler.
 *
 * docker exec -e DB_DATABASE=alatax_hr_testing alatax-hr-app php scripts/uzun-kosu/clean-install-proof.php
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dbName = (string) config('database.connections.'.config('database.default').'.database');
if ($dbName !== 'alatax_hr_testing') {
    fwrite(STDERR, "ABORT: {$dbName}\n");
    exit(1);
}

$out = ['db' => $dbName, 'steps' => []];

$step = function (string $name, callable $fn) use (&$out): void {
    $t0 = microtime(true);
    $error = null;
    $result = null;
    try {
        $result = $fn();
    } catch (Throwable $e) {
        $error = $e::class.': '.$e->getMessage();
    }
    $out['steps'][] = [
        'step' => $name,
        'elapsed_sec' => round(microtime(true) - $t0, 2),
        'result' => $result,
        'error' => $error,
    ];
    echo $name.' '.($error ? 'FAIL '.$error : 'OK').PHP_EOL;
};

$tAll = microtime(true);

$step('migrate:fresh', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);

    return ['output_tail' => substr(Artisan::output(), -800)];
});

$step('db:seed PermissionSeeder+DatabaseSeeder', function () {
    Artisan::call('db:seed', ['--force' => true]);

    return ['output_tail' => substr(Artisan::output(), -800)];
});

$step('check_panel_access_column', function () {
    $has = Schema::hasColumn('roles', 'panel_access');
    $emp = DB::table('roles')->where('name', 'employee')->value('panel_access');
    $admin = DB::table('roles')->where('name', 'admin')->value('panel_access');
    $roleCount = DB::table('roles')->count();

    return [
        'has_column' => $has,
        'role_count' => $roleCount,
        'employee_panel_access' => $emp,
        'admin_panel_access' => $admin,
        'full_name_column' => Schema::hasColumn('employees', 'full_name'),
    ];
});

$step('demo:seed', function () {
    Artisan::call('demo:seed');

    return ['output' => trim(Artisan::output())];
});

$step('demo:align-organizations', function () {
    Artisan::call('demo:align-organizations');

    return ['output' => trim(Artisan::output())];
});

$step('verify_demo_org', function () {
    $slugs = ['demo-firma', 'demo-otel-b', 'demo-otel-c'];
    $companies = DB::table('companies')->whereIn('slug', $slugs)->get(['id', 'slug', 'organization_id']);
    $orgIds = $companies->pluck('organization_id')->unique()->filter()->values();
    $admin = DB::table('users')->where('email', 'admin@demo.test')->first();
    $memberships = $admin
        ? DB::table('company_user')->where('user_id', $admin->id)->whereIn('company_id', $companies->pluck('id'))->pluck('company_id')
        : collect();

    return [
        'companies' => $companies,
        'unique_org_ids' => $orgIds,
        'single_org' => $orgIds->count() === 1,
        'admin_id' => $admin->id ?? null,
        'admin_memberships' => $memberships->values(),
        'admin_member_of_all_three' => $memberships->count() === 3,
    ];
});

$out['total_elapsed_sec'] = round(microtime(true) - $tAll, 2);
file_put_contents(__DIR__.'/clean-install.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
