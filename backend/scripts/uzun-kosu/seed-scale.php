<?php

/**
 * UZUN KOŞU — ölçekli sentetik veri (yalnız dobedan_hr_testing).
 * Idempotent: slug scale-co-{1,2,3} silinip yeniden üretilir.
 *
 * docker compose exec -e DB_DATABASE=dobedan_hr_testing -e SCALE_SEED_PASSWORD=<sentetik-kullanıcı-şifresi> app php scripts/uzun-kosu/seed-scale.php
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dbName = (string) config('database.connections.'.config('database.default').'.database');
if ($dbName !== 'dobedan_hr_testing') {
    fwrite(STDERR, "ABORT: DB must be dobedan_hr_testing, got [{$dbName}]\n");
    exit(1);
}

$EMP_PER_CO = 1000;
$ATT_DAYS = 500; // 3*1000*500 = 1_500_000
$LEAVE_TARGET = 50_000;
$DOC_TARGET = 20_000;
$MARKER = 'uzun-kosu-scale-v1';

$plainPassword = (string) getenv('SCALE_SEED_PASSWORD');
if (strlen($plainPassword) < 8) {
    fwrite(STDERR, "ABORT: SCALE_SEED_PASSWORD (en az 8 karakter) ortam değişkeni gerekli\n");
    exit(1);
}
$t0 = microtime(true);
$now = now()->toDateTimeString();

echo "DB={$dbName} marker={$MARKER}\n";

$oldIds = DB::table('companies')->where('slug', 'like', 'scale-co-%')->pluck('id')->all();
if ($oldIds !== []) {
    echo 'Cleanup: '.implode(',', $oldIds)."\n";
    $userIds = DB::table('users')->whereIn('home_company_id', $oldIds)->pluck('id')->all();
    foreach (['attendance_records', 'leave_requests', 'employee_documents', 'employees', 'positions', 'branches', 'departments', 'leave_types'] as $t) {
        if (Schema::hasTable($t)) {
            DB::table($t)->whereIn('company_id', $oldIds)->delete();
        }
    }
    if ($userIds !== []) {
        DB::table('model_has_roles')->whereIn('model_id', $userIds)->where('model_type', 'like', '%User%')->delete();
        if (Schema::hasTable('company_user')) {
            DB::table('company_user')->whereIn('user_id', $userIds)->delete();
        }
        DB::table('users')->whereIn('id', $userIds)->delete();
    }
    DB::table('companies')->whereIn('id', $oldIds)->delete();
}

$password = Hash::make($plainPassword);
$companyIds = [];
$branchIds = [];
$deptIds = [];
$leaveTypeIds = [];
$allUserIdsByCo = [];

for ($c = 1; $c <= 3; $c++) {
    $cid = DB::table('companies')->insertGetId([
        'name' => "Scale Co {$c}",
        'slug' => "scale-co-{$c}",
        'email' => "admin-scale-{$c}@example.test",
        'country' => 'Türkiye',
        'package_type' => 'starter',
        'user_limit' => 5000,
        'storage_limit' => 1073741824,
        'status' => 'active',
        'location_count' => 1,
        'location_limit' => 10,
        'employee_limit' => 5000,
        'current_balance' => 0,
        'settings' => json_encode(['marker' => $MARKER], JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $companyIds[] = $cid;

    $adminId = DB::table('users')->insertGetId([
        'company_id' => $cid,
        'name' => "Scale Admin {$c}",
        'email' => "admin-scale-{$c}@example.test",
        'password' => $password,
        'type' => 'company_admin',
        'is_active' => true,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if (Schema::hasTable('company_user')) {
        DB::table('company_user')->insert([
            'company_id' => $cid,
            'user_id' => $adminId,
            'is_default' => true,
            'created_at' => $now,
        ]);
    }

    $branchIds[$cid] = DB::table('branches')->insertGetId([
        'company_id' => $cid,
        'name' => "Scale Branch {$c}",
        'code' => "SCB{$c}",
        'country' => 'Türkiye',
        'is_active' => true,
        'is_headquarters' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $deptIds[$cid] = DB::table('departments')->insertGetId([
        'company_id' => $cid,
        'name' => "Scale Dept {$c}",
        'code' => "SCD{$c}",
        'is_active' => true,
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $leaveTypeIds[$cid] = DB::table('leave_types')->insertGetId([
        'company_id' => $cid,
        'name' => 'Yıllık',
        'code' => 'ANNUAL',
        'default_days' => 14,
        'is_active' => true,
        'is_paid' => true,
        'gender_restriction' => 'all',
        'min_days_notice' => 0,
        'is_system' => false,
        'deducts_from_annual' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Pozisyon kataloğu (code/name karma ölçümü için)
    foreach ([
        ['code' => 'DEMO_POS_07', 'name' => 'Kıdemli Yazılım Geliştirici'],
        ['code' => 'YAZ_KID', 'name' => 'Kıdemli Yazılım Geliştirici'],
        ['code' => 'MGR_01', 'name' => 'Yönetici'],
    ] as $p) {
        DB::table('positions')->insert([
            'company_id' => $cid,
            'code' => $p['code'],
            'name' => $p['name'],
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    echo "Company {$c} id={$cid}\n";
}

$posCodes = ['DEMO_POS_07', 'YAZ_KID', 'MGR_01'];
$posNames = ['Kıdemli Yazılım Geliştirici', 'Yönetici', 'Uzman'];

foreach ($companyIds as $ci => $cid) {
    $userBatch = [];
    for ($i = 1; $i <= $EMP_PER_CO; $i++) {
        $userBatch[] = [
            'company_id' => $cid,
            'name' => 'Scale Emp '.($ci + 1)."-{$i}",
            'email' => sprintf('scale-e%02d-%04d@example.test', $ci + 1, $i),
            'password' => $password,
            'type' => 'user',
            'is_active' => true,
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (count($userBatch) >= 500) {
            DB::table('users')->insert($userBatch);
            $userBatch = [];
        }
    }
    if ($userBatch !== []) {
        DB::table('users')->insert($userBatch);
    }

    $rows = DB::table('users')
        ->where('company_id', $cid)
        ->where('email', 'like', 'scale-e%')
        ->orderBy('id')
        ->get(['id']);

    $empBatch = [];
    $uids = [];
    foreach ($rows as $idx => $u) {
        $uids[] = (int) $u->id;
        $mod = $idx % 3;
        $position = match ($mod) {
            0 => $posCodes[$idx % 3],
            1 => $posNames[$idx % 3],
            default => 'ORPHAN_POS_'.$idx,
        };
        $empBatch[] = [
            'company_id' => $cid,
            'user_id' => $u->id,
            'department_id' => $deptIds[$cid],
            'branch_id' => $branchIds[$cid],
            'employee_code' => sprintf('SC%02d-%04d', $ci + 1, $idx + 1),
            'full_name' => 'Scale Emp '.($ci + 1).'-'.($idx + 1),
            // position string dropped

            'status' => 'active',
            'hire_date' => '2023-01-15',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (count($empBatch) >= 500) {
            DB::table('employees')->insert($empBatch);
            $empBatch = [];
        }
    }
    if ($empBatch !== []) {
        DB::table('employees')->insert($empBatch);
    }
    $allUserIdsByCo[$cid] = $uids;
    echo 'Employees co='.$cid.' count='.count($uids)."\n";
}

// Puantaj: generate_series (hızlı)
echo "Attendance generate_series days={$ATT_DAYS}\n";
foreach ($companyIds as $cid) {
    DB::statement("
        INSERT INTO attendance_records (
            company_id, user_id, date, clock_in, clock_out, total_hours, overtime_hours,
            clock_in_method, status, is_approved, late_minutes, early_leave_minutes, missing_minutes,
            source, branch_id, created_at, updated_at
        )
        SELECT
            ?,
            u.id,
            gs::date,
            '09:00:00'::time,
            '18:00:00'::time,
            8,
            0,
            'qr',
            'present',
            true,
            0, 0, 0,
            'qr',
            ?,
            ?::timestamp,
            ?::timestamp
        FROM users u
        CROSS JOIN generate_series(DATE '2024-01-01', DATE '2024-01-01' + (?::int - 1), INTERVAL '1 day') AS gs
        WHERE u.company_id = ?
          AND u.email LIKE 'scale-e%'
        ON CONFLICT (user_id, date) DO NOTHING
    ", [$cid, $branchIds[$cid], $now, $now, $ATT_DAYS, $cid]);
    $cnt = DB::table('attendance_records')->where('company_id', $cid)->count();
    echo "  co={$cid} attendance={$cnt}\n";
}

// İzin — generate + batch
$leaveInserted = 0;
$statuses = ['pending', 'approved', 'rejected', 'cancelled'];
$perCoLeave = (int) ceil($LEAVE_TARGET / 3);
foreach ($companyIds as $cid) {
    $uids = $allUserIdsByCo[$cid];
    $lt = $leaveTypeIds[$cid];
    $batch = [];
    for ($n = 0; $n < $perCoLeave; $n++) {
        $uid = $uids[$n % count($uids)];
        $month = 1 + ($n % 12);
        $day = 1 + ($n % 28);
        $start = sprintf('2025-%02d-%02d', $month, $day);
        $batch[] = [
            'company_id' => $cid,
            'user_id' => $uid,
            'leave_type_id' => $lt,
            'start_date' => $start,
            'end_date' => $start,
            'total_days' => 1,
            'reason' => 'scale',
            'status' => $statuses[$n % 4],
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (count($batch) >= 1000) {
            DB::table('leave_requests')->insert($batch);
            $leaveInserted += count($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        DB::table('leave_requests')->insert($batch);
        $leaveInserted += count($batch);
    }
}
echo "Leave inserted={$leaveInserted}\n";

// Dokümanlar
$docInserted = 0;
$perCoDoc = (int) ceil($DOC_TARGET / 3);
foreach ($companyIds as $cid) {
    $empIds = DB::table('employees')->where('company_id', $cid)->orderBy('id')->pluck('id')->all();
    $batch = [];
    for ($n = 0; $n < $perCoDoc; $n++) {
        $eid = $empIds[$n % count($empIds)];
        $batch[] = [
            'company_id' => $cid,
            'employee_id' => $eid,
            'title' => 'Scale Doc '.$n,
            'category' => 'other',
            'file_path' => 'scale/doc-'.$cid.'-'.$n.'.pdf',
            'file_name' => 'doc-'.$n.'.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 1024,
            'expiry_date' => ($n % 5 === 0) ? '2026-09-01' : null,
            'is_expired' => false,
            'is_visible_to_employee' => true,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (count($batch) >= 500) {
            DB::table('employee_documents')->insert($batch);
            $docInserted += count($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        DB::table('employee_documents')->insert($batch);
        $docInserted += count($batch);
    }
}
echo "Docs inserted={$docInserted}\n";

$summary = [
    'companies' => $companyIds,
    'employees' => DB::table('employees')->whereIn('company_id', $companyIds)->count(),
    'users_scale' => DB::table('users')->whereIn('home_company_id', $companyIds)->where('email', 'like', 'scale-e%')->count(),
    'attendance' => DB::table('attendance_records')->whereIn('company_id', $companyIds)->count(),
    'leave_requests' => DB::table('leave_requests')->whereIn('company_id', $companyIds)->count(),
    'employee_documents' => DB::table('employee_documents')->whereIn('company_id', $companyIds)->count(),
    'elapsed_sec' => round(microtime(true) - $t0, 1),
];
file_put_contents(__DIR__.'/scale-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
echo "DONE\n";
