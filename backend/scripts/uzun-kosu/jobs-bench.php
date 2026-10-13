<?php

/**
 * UZUN KOŞU adım 5 — kuyruk / zamanlanmış işler (alatax_hr_testing).
 */

declare(strict_types=1);

use App\Jobs\ProcessHeavyReportExportJob;
use App\Jobs\RunReportScheduleJob;
use App\Models\Company;
use App\Models\ReportSchedule;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Approval\ApprovalEscalationService;
use App\Services\Documents\DocumentExpiryAlertService;
use App\Services\Leaves\LeaveAccrualBatchService;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Artisan;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dbName = (string) config('database.connections.'.config('database.default').'.database');
if ($dbName !== 'alatax_hr_testing') {
    fwrite(STDERR, "ABORT testing DB\n");
    exit(1);
}

$company = Company::query()->where('slug', 'scale-co-1')->first();
$admin = $company
    ? User::query()->where('home_company_id', $company->id)->where('email', 'admin-scale-1@example.test')->first()
    : null;

function bench(string $name, callable $fn): array
{
    $mem0 = memory_get_usage(true);
    $t0 = microtime(true);
    $payload = null;
    $error = null;
    try {
        $payload = $fn();
    } catch (Throwable $e) {
        $error = $e::class.': '.$e->getMessage();
    }

    return [
        'job' => $name,
        'elapsed_ms' => round((microtime(true) - $t0) * 1000, 1),
        'mem_delta_mb' => round((memory_get_usage(true) - $mem0) / 1048576, 2),
        'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        'result' => $payload,
        'error' => $error,
    ];
}

$out = [];

$out[] = bench('LeaveAccrualBatch_allActive', function () {
    return app(LeaveAccrualBatchService::class)->processMonthlyForAllActiveCompanies((int) now()->month, (int) now()->year);
});

$out[] = bench('DocumentExpiryAlert_processCompany_WITHOUT_context', function () use ($company) {
    try {
        app(DocumentExpiryAlertService::class)->processCompany((int) $company->id);

        return ['unexpected' => 'no exception'];
    } catch (Throwable $e) {
        return ['throws' => $e::class, 'message' => $e->getMessage()];
    }
});

$out[] = bench('DocumentExpiryAlert_processCompany_WITH_context', function () use ($company) {
    return CompanyContext::run((int) $company->id, fn () => app(DocumentExpiryAlertService::class)->processCompany((int) $company->id));
});

$out[] = bench('DocumentExpiryAlert_processAllActive', function () {
    return app(DocumentExpiryAlertService::class)->processAllActiveCompanies();
});

$out[] = bench('ApprovalEscalation_processCompany_WITHOUT_context', function () use ($company) {
    try {
        app(ApprovalEscalationService::class)->processCompany((int) $company->id);

        return ['unexpected' => 'no exception'];
    } catch (Throwable $e) {
        return ['throws' => $e::class, 'message' => $e->getMessage()];
    }
});

$out[] = bench('ApprovalEscalation_processCompany_WITH_context', function () use ($company) {
    return CompanyContext::run((int) $company->id, fn () => app(ApprovalEscalationService::class)->processCompany((int) $company->id));
});

$out[] = bench('kvkk:scan-retention', function () use ($company) {
    Artisan::call('kvkk:scan-retention', ['--company' => $company->id]);

    return ['output' => trim(Artisan::output())];
});

$out[] = bench('ProcessHeavyReportExportJob_companyId_0', function () {
    $job = new ProcessHeavyReportExportJob(1, 1, 0, 'company', null);
    try {
        app()->call([$job, 'handle']);

        return ['unexpected' => 'no exception'];
    } catch (Throwable $e) {
        return ['throws' => $e::class, 'message' => $e->getMessage()];
    }
});

$out[] = bench('ProcessHeavyReportExportJob_with_company', function () use ($company, $admin) {
    $report = SavedReport::query()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
        'name' => 'uzun-kosu-export-'.time(),
        'dataset_key' => 'employees',
        'config' => [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'status', 'position'],
            'limit' => 1000,
        ],
        'is_shared' => false,
    ]);
    $job = new ProcessHeavyReportExportJob((int) $report->id, (int) $admin->id, (int) $company->id, 'company', null);
    app()->call([$job, 'handle']);

    return ['report_id' => $report->id];
});

$out[] = bench('RunReportScheduleJob', function () use ($company, $admin) {
    $report = SavedReport::query()->firstOrCreate(
        ['company_id' => $company->id, 'name' => 'uzun-kosu-sched'],
        [
            'user_id' => $admin->id,
            'dataset_key' => 'employees',
            'config' => ['dataset' => 'employees', 'fields' => ['employee_code'], 'limit' => 50],
            'is_shared' => false,
        ]
    );
    $sched = ReportSchedule::query()->create([
        'company_id' => $company->id,
        'report_id' => $report->id,
        'owner_id' => $admin->id,
        'name' => 'uzun-kosu-'.time(),
        'cadence' => 'daily',
        'hour' => 6,
        'minute' => 0,
        'timezone' => 'Europe/Istanbul',
        'format' => 'link',
        'recipients' => [$admin->email],
        'active' => true,
        'next_run_at' => now()->subMinute(),
    ]);
    $job = new RunReportScheduleJob((int) $sched->id);
    app()->call([$job, 'handle']);

    return ['schedule_id' => $sched->id];
});

file_put_contents(__DIR__.'/jobs-summary.json', json_encode(['jobs' => $out, 'scale_company_id' => $company?->id], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
