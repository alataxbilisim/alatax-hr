<?php

/**
 * User sorgularında where('company_id' → where('home_company_id'
 * ve eager-load select company_id → home_company_id.
 * Entity company_id dokunulmaz — yalnız User::... blokları.
 */

$files = [
    'app/Services/Documents/DocumentExpiryAlertService.php',
    'app/Services/Recruitment/ConvertApplicationToEmployeeService.php',
    'app/Services/Announcements/AnnouncementPublishService.php',
    'app/Services/Reports/ReportDefinitionService.php',
    'app/Services/Reports/ReportScheduleRecipientResolver.php',
    'app/Services/Approval/ApprovalEscalationService.php',
    'app/Http/Controllers/Api/V1/Leaves/LeaveBalanceController.php',
    'app/Console/Commands/ScanRetentionCommand.php',
    'app/Http/Controllers/Api/V1/Portal/PortalPerformanceController.php',
];

$root = dirname(__DIR__);
foreach ($files as $rel) {
    $path = $root.'/'.$rel;
    $src = file_get_contents($path);
    $orig = $src;

    // Eager load columns on user relation
    $src = str_replace(
        'user:id,name,email,company_id,preferences',
        'user:id,name,email,home_company_id,preferences',
        $src
    );

    // User::query()\n->where('company_id'
    $src = preg_replace(
        '/(User::(?:query\(\))?\s*(?:->[a-zA-Z_]+\([^)]*\))*\s*->where\()([\'"])company_id\2/',
        '$1$2home_company_id$2',
        $src
    );

    // Multi-line: User::query()\n                ->where('company_id'
    $src = preg_replace(
        '/(User::query\(\)\s*\n\s*->where\()([\'"])company_id\2/',
        '$1$2home_company_id$2',
        $src
    );

    // \App\Models\User::where('id'...)->where('company_id'
    $src = preg_replace(
        '/(\\\\App\\\\Models\\\\User::where\([^\n]+)\n(\s*->where\()([\'"])company_id\3/',
        "$1\n$2$3home_company_id$3",
        $src
    );

    // User::where('id', ...)->where('company_id' same line or next
    $src = preg_replace(
        '/(User::where\([^\n;]*?)(->where\()([\'"])company_id\3/',
        '$1$2$3home_company_id$3',
        $src
    );

    if ($src !== $orig) {
        file_put_contents($path, $src);
        echo "UPDATED {$rel}\n";
    } else {
        echo "NOCHANGE {$rel}\n";
    }
}

// Second pass: verify remaining User where company_id in app/
passthru('php '.escapeshellarg(__DIR__.'/find-user-company-id.php').' 2>&1 | grep -E "User::|user:id|DocumentExpiry|ConvertApplication|AnnouncementPublish|ReportDefinition|ReportSchedule|ApprovalEscalation|LeaveBalance|ScanRetention|PortalPerformance" || true');
