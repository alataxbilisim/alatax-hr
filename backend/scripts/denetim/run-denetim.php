<?php

/**
 * Faz 6 öncesi DENETİM TURU — teşhis (ürün kodu değiştirmez).
 * Çalıştır: docker exec dobedan-hr-app php scripts/denetim/run-denetim.php
 * DB: dobedan_hr_testing (migrate:fresh --seed önce).
 */

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\Timesheet\AttendanceClockService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Testing DB'ye zorla
config(['database.connections.pgsql.database' => 'dobedan_hr_testing']);
config(['database.default' => 'pgsql']);
DB::purge('pgsql');
DB::reconnect('pgsql');

$out = [
    'meta' => [
        'started_at' => now()->toIso8601String(),
        'db' => DB::connection()->getDatabaseName(),
        'app_timezone' => config('app.timezone'),
        'queue_default' => config('queue.default'),
        'queue_failed' => config('queue.failed'),
        'mail_default' => config('mail.default'),
        'filesystem_default' => config('filesystems.default'),
    ],
    'cascade' => [],
    'timezone' => [],
    'money' => [],
    'turkish' => [],
    'files' => [],
    'queue' => [],
    'soft_unique' => [],
    'email' => [],
];

function catchResult(callable $fn): array
{
    try {
        $r = $fn();

        return ['ok' => true, 'result' => $r];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'sqlstate' => method_exists($e, 'getCode') ? (string) $e->getCode() : null,
        ];
    }
}

if (getenv('DENETIM_SKIP_FRESH') !== '1') {
    echo "migrate:fresh --seed on dobedan_hr_testing...\n";
    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    echo Artisan::output();
} else {
    echo "SKIP fresh — using existing dobedan_hr_testing\n";
}

// ─── 1) CASCADE MATRIX ───────────────────────────────────────────
echo "1) cascade matrix...\n";

$uid = uniqid('den_', true);
$company = Company::factory()->create(['status' => CompanyStatus::Active, 'name' => 'DENETIM Cascade Co']);
$admin = User::factory()->create([
    'company_id' => $company->id,
    'type' => UserType::CompanyAdmin,
    'email' => "denetim-cascade-admin-{$uid}@example.com",
    'email_verified_at' => now(),
]);

CompanyContext::run($company->id, function () use ($company, $admin, $uid, &$out) {
    $branch = Branch::create([
        'company_id' => $company->id,
        'name' => 'Merkez',
        'code' => 'DEN-BR-1',
        'is_active' => true,
    ]);
    $dept = Department::create([
        'company_id' => $company->id,
        'name' => 'İK',
        'code' => 'DEN-DEP-1',
        'is_active' => true,
    ]);
    $pos = Position::create([
        'company_id' => $company->id,
        'code' => 'DEN_POS_1',
        'name' => 'Uzman',
        'is_active' => true,
        'is_system' => false,
    ]);
    $emp = Employee::create([
        'company_id' => $company->id,
        'employee_code' => 'DEN-E-1',
        'full_name' => 'Cascade Personel',
        'national_id' => '11111111110',
        'department_id' => $dept->id,
        'branch_id' => $branch->id,
        'position_id' => $pos->id,
        // position string dropped

        'status' => 'active',
        'user_id' => null,
        'created_by' => $admin->id,
    ]);

    // A) Soft-delete company with branch
    $co2 = Company::factory()->create(['status' => CompanyStatus::Active, 'name' => 'DENETIM Soft Co']);
    $br2 = Branch::withoutGlobalScopes()->create([
        'company_id' => $co2->id,
        'name' => 'Şube Soft',
        'code' => 'SOFT-BR',
        'is_active' => true,
    ]);
    $soft = catchResult(fn () => $co2->delete());
    $brAfterSoft = Branch::withoutGlobalScopes()->withTrashed()->find($br2->id);
    $coAfterSoft = Company::withTrashed()->find($co2->id);
    $out['cascade']['company_soft_delete_with_branch'] = [
        'action' => 'Company::delete() (soft)',
        'delete_result' => $soft,
        'company_deleted_at' => $coAfterSoft?->deleted_at?->toIso8601String(),
        'branch_still_exists' => $brAfterSoft !== null,
        'branch_deleted_at' => $brAfterSoft?->deleted_at?->toIso8601String(),
        'observed' => $soft['ok']
            ? 'Şirket soft-delete OK; şube satırı duruyor (cascade yok — soft delete FK tetiklemez)'
            : 'Soft delete başarısız',
    ];

    // B) Hard-delete company with branch (forceDelete)
    $co3 = Company::factory()->create(['status' => CompanyStatus::Active, 'name' => 'DENETIM Hard Co']);
    $br3 = Branch::withoutGlobalScopes()->create([
        'company_id' => $co3->id,
        'name' => 'Şube Hard',
        'code' => 'HARD-BR',
        'is_active' => true,
    ]);
    $hard = catchResult(fn () => $co3->forceDelete());
    $brAfterHard = Branch::withoutGlobalScopes()->withTrashed()->find($br3->id);
    $out['cascade']['company_force_delete_with_branch'] = [
        'action' => 'Company::forceDelete()',
        'delete_result' => $hard,
        'branch_still_exists' => $brAfterHard !== null,
        'observed' => $hard['ok']
            ? ($brAfterHard === null
                ? 'Şirket hard-delete + şube CASCADE silindi'
                : 'Şirket silindi ama şube yetim kaldı')
            : 'Hard delete FK/başka hata ile engellendi: '.($hard['message'] ?? ''),
    ];

    // C) Department with employees — soft delete (Eloquent, API guard yok)
    $deptDel = catchResult(fn () => $dept->delete());
    $empAfterDept = Employee::withTrashed()->find($emp->id);
    $out['cascade']['department_soft_delete_with_employee'] = [
        'action' => 'Department::delete() soft (API engeli atlandı)',
        'delete_result' => $deptDel,
        'employee_department_id_after' => $empAfterDept?->department_id,
        'employee_exists' => $empAfterDept !== null,
        'api_guard' => 'DepartmentController::destroy personel varsa 422 döner',
        'observed' => $deptDel['ok']
            ? 'Soft-delete departman OK; employee.department_id hâlâ eski id (SET NULL soft’ta çalışmaz)'
            : 'Engellendi',
    ];
    // restore for further tests
    if ($dept->trashed()) {
        $dept->restore();
    }

    // D) Position with employees — soft delete
    $posDel = catchResult(fn () => $pos->delete());
    $empAfterPos = Employee::withTrashed()->find($emp->id);
    $out['cascade']['position_soft_delete_with_employee'] = [
        'action' => 'Position::delete() soft',
        'delete_result' => $posDel,
        'employee_position_id_after' => $empAfterPos?->position_id,
        'api_guard' => 'PositionController yalnızca is_system engeller; personel kontrolü YOK',
        'observed' => $posDel['ok']
            ? 'Pozisyon soft-delete OK; employee.position_id eski id’de kalır (nullOnDelete soft’ta tetiklenmez)'
            : 'Engellendi',
    ];
    if ($pos->trashed()) {
        $pos->restore();
    }

    // E) Position forceDelete with employee FK nullOnDelete
    $pos2 = Position::create([
        'company_id' => $company->id,
        'code' => 'DEN_POS_FORCE',
        'name' => 'Force Pos',
        'is_active' => true,
        'is_system' => false,
    ]);
    $emp->update(['position_id' => $pos2->id]);
    $posForce = catchResult(fn () => $pos2->forceDelete());
    $emp->refresh();
    $out['cascade']['position_force_delete_with_employee'] = [
        'action' => 'Position::forceDelete() (FK nullOnDelete)',
        'delete_result' => $posForce,
        'employee_position_id_after' => $emp->position_id,
        'observed' => $posForce['ok']
            ? 'Hard-delete pozisyon → employee.position_id SET NULL'
            : 'FK hatası: '.($posForce['message'] ?? ''),
    ];
    $emp->update(['position_id' => $pos->id]);

    // F) Employee with leave + assignment + document — soft delete
    $leaveType = LeaveType::firstOrCreate(
        ['company_id' => $company->id, 'code' => 'DEN_LV'],
        ['name' => 'Denetim İzin', 'is_active' => true, 'default_days' => 14, 'is_paid' => true]
    );
    $portalUser = User::factory()->create([
        'company_id' => $company->id,
        'type' => UserType::User,
        'email' => "denetim-emp-user-{$uid}@example.com",
    ]);
    $emp->update(['user_id' => $portalUser->id]);
    $leave = LeaveRequest::create([
        'company_id' => $company->id,
        'user_id' => $portalUser->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'total_days' => 1,
        'status' => 'pending',
    ]);
    $cat = AssetCategory::firstOrCreate(
        ['company_id' => $company->id, 'name' => 'Denetim Cat'],
        ['is_active' => true]
    );
    $asset = Asset::create([
        'company_id' => $company->id,
        'category_id' => $cat->id,
        'name' => 'Laptop Denetim',
        'asset_code' => 'DEN-AST-1',
        'status' => 'available',
    ]);
    $assign = AssetAssignment::create([
        'asset_id' => $asset->id,
        'user_id' => $portalUser->id,
        'assigned_date' => now()->toDateString(),
        'assigned_by' => $admin->id,
    ]);
    $doc = EmployeeDocument::create([
        'company_id' => $company->id,
        'employee_id' => $emp->id,
        'title' => 'Kimlik',
        'category' => 'identity',
        'file_path' => 'employees/'.$emp->id.'/fake.pdf',
        'file_name' => 'fake.pdf',
        'status' => 'active',
    ]);

    $leaveCountBefore = LeaveRequest::where('user_id', $portalUser->id)->count();
    $assignCountBefore = AssetAssignment::where('user_id', $portalUser->id)->count();
    $docCountBefore = EmployeeDocument::where('employee_id', $emp->id)->count();
    $empSoft = catchResult(fn () => $emp->delete());
    $leaveAfter = LeaveRequest::withTrashed()->find($leave->id);
    $assignAfter = AssetAssignment::find($assign->id);
    $docAfter = EmployeeDocument::withTrashed()->find($doc->id);
    $out['cascade']['employee_soft_delete_with_relations'] = [
        'action' => 'Employee::delete() soft (API aynı)',
        'delete_result' => $empSoft,
        'before' => compact('leaveCountBefore', 'assignCountBefore', 'docCountBefore'),
        'leave_still_exists' => $leaveAfter !== null,
        'leave_deleted_at' => $leaveAfter?->deleted_at?->toIso8601String(),
        'assignment_still_exists' => $assignAfter !== null,
        'document_still_exists' => $docAfter !== null,
        'document_deleted_at' => $docAfter?->deleted_at?->toIso8601String(),
        'file_on_disk_after_soft' => 'soft delete dosya silmez (EmployeeDocumentController::destroy dosyayı siler; employee destroy çağırmaz)',
        'observed' => 'Personel soft-delete; izin/zimmet/evrak satırları yerinde (orphan ilişki / soft parent). Zimmet user_id üzerinden.',
    ];

    // G) User with role — soft delete
    $roleUser = User::factory()->create([
        'company_id' => $company->id,
        'email' => "denetim-role-user-{$uid}@example.com",
        'type' => UserType::User,
    ]);
    $customRole = Role::create([
        'name' => 'denetim_custom_role_'.$uid,
        'guard_name' => 'sanctum',
    ]);
    $roleUser->assignRole($customRole);
    $pivotBefore = DB::table('model_has_roles')->where('role_id', $customRole->id)->count();
    $userSoft = catchResult(fn () => $roleUser->delete());
    $pivotAfterSoft = DB::table('model_has_roles')->where('role_id', $customRole->id)->count();
    $out['cascade']['user_soft_delete_with_role'] = [
        'action' => 'User::delete() soft',
        'delete_result' => $userSoft,
        'pivot_before' => $pivotBefore,
        'pivot_after_soft' => $pivotAfterSoft,
        'api_guard' => 'UserController::destroy engel yok (kendini silme hariç)',
        'observed' => $pivotAfterSoft > 0
            ? 'Kullanıcı soft-delete; model_has_roles pivot satırı KALDI (yetim pivot)'
            : 'Pivot temizlendi',
    ];

    // H) Role used in approval step + assigned — destroy via Eloquent
    $wf = ApprovalWorkflow::create([
        'company_id' => $company->id,
        'name' => 'Denetim WF',
        'entity_type' => 'leave_request',
        'is_active' => true,
    ]);
    $stepRole = Role::create([
        'name' => 'denetim_approver_role_'.$uid,
        'guard_name' => 'sanctum',
    ]);
    $step = ApprovalStep::create([
        'approval_workflow_id' => $wf->id,
        'step_order' => 1,
        'name' => 'Rol Onayı',
        'approver_type' => 'specific_role',
        'specific_role' => $stepRole->name,
        'is_required' => true,
    ]);
    $assignee = User::factory()->create([
        'company_id' => $company->id,
        'email' => "denetim-assignee-{$uid}@example.com",
    ]);
    $assignee->assignRole($stepRole);

    $apiWouldBlock = DB::table('model_has_roles')->where('role_id', $stepRole->id)->count() > 0;
    // Spatie genelde hard delete; API kullanıcı varken engeller — Eloquent denemesi
    $roleDelWithUsers = catchResult(fn () => $stepRole->delete());
    $stepAfter = ApprovalStep::find($step->id);
    $pivotAfterRoleDel = DB::table('model_has_roles')->where('role_id', $stepRole->id)->count();
    $out['cascade']['role_delete_with_users_and_approval_step'] = [
        'action' => 'Role::delete() while users assigned + approval_steps.specific_role=name (string, FK yok)',
        'api_guard' => 'RoleController::destroy kullanıcı varsa 400; protected role 403',
        'api_would_block_users' => $apiWouldBlock,
        'delete_with_users' => $roleDelWithUsers,
        'pivot_after_delete' => $pivotAfterRoleDel,
        'step_still_exists' => $stepAfter !== null,
        'step_specific_role_value' => $stepAfter?->specific_role,
        'observed' => $roleDelWithUsers['ok']
            ? 'Spatie Role hard-delete OK (API engeli yoksa); approval_steps.specific_role string YETİM; pivot genelde cascade/cleanup'
            : 'Role delete engellendi/hata: '.($roleDelWithUsers['message'] ?? ''),
    ];

    if (Role::whereKey($stepRole->id)->exists()) {
        DB::table('model_has_roles')->where('role_id', $stepRole->id)->delete();
        $roleDelClean = catchResult(fn () => $stepRole->delete());
        $stepAfter2 = ApprovalStep::find($step->id);
        $out['cascade']['role_delete_after_detach'] = [
            'delete_result' => $roleDelClean,
            'step_specific_role_orphaned' => $stepAfter2?->specific_role,
            'observed' => 'Rol silindi; adım specific_role string olarak duruyor — runtime’da rol bulunamaz',
        ];
    } else {
        $out['cascade']['role_delete_after_detach'] = [
            'observed' => 'Rol zaten silindi; step.specific_role='.($stepAfter?->specific_role ?? 'null'),
        ];
    }
});

// ─── 2) TIMEZONE ─────────────────────────────────────────────────
echo "2) timezone...\n";
$dbTz = DB::selectOne('SHOW timezone');
$colTypes = DB::select("
    SELECT column_name, data_type, udt_name
    FROM information_schema.columns
    WHERE table_name = 'attendance_records'
      AND column_name IN ('date','clock_in','clock_out','created_at','updated_at')
    ORDER BY column_name
");
$out['timezone']['app_timezone'] = config('app.timezone');
$out['timezone']['php_date_default_timezone'] = date_default_timezone_get();
$out['timezone']['carbon_now'] = Carbon::now()->toIso8601String();
$out['timezone']['db_show_timezone'] = $dbTz->TimeZone ?? $dbTz->timezone ?? json_encode($dbTz);
$out['timezone']['attendance_columns'] = $colTypes;

$tzCompany = Company::factory()->create(['status' => CompanyStatus::Active, 'name' => 'DENETIM TZ']);
$tzUser = User::factory()->create([
    'company_id' => $tzCompany->id,
    'email' => "denetim-tz-{$uid}@example.com",
    'type' => UserType::User,
]);
Employee::create([
    'company_id' => $tzCompany->id,
    'user_id' => $tzUser->id,
    'employee_code' => 'TZ-'.$uid,
    'full_name' => 'TZ Person',
    'status' => 'active',
]);

$svc = app(AttendanceClockService::class);

// Simulate 23:30 Europe/Istanbul = 20:30 UTC
Carbon::setTestNow(Carbon::parse('2026-08-05 20:30:00', 'UTC'));
$punchLate = catchResult(fn () => CompanyContext::run($tzCompany->id, fn () => $svc->clockIn($tzUser, ['source' => 'portal'])));
$recLate = AttendanceRecord::withoutGlobalScopes()->where('user_id', $tzUser->id)->latest('id')->first();
$out['timezone']['punch_20_30_utc_as_istanbul_23_30'] = [
    'test_now_utc' => '2026-08-05 20:30:00 UTC (= 23:30 TR)',
    'result' => $punchLate,
    'stored_date' => $recLate ? (string) $recLate->getRawOriginal('date') : null,
    'stored_clock_in' => $recLate?->clock_in,
    'note' => 'AttendanceClockService uses now()->toDateString() with app timezone',
];

// Clean for next punch day
AttendanceRecord::withoutGlobalScopes()->where('user_id', $tzUser->id)->forceDelete();

Carbon::setTestNow(Carbon::parse('2026-08-05 21:30:00', 'UTC')); // 00:30 TR next calendar day
$punchEarly = catchResult(fn () => CompanyContext::run($tzCompany->id, fn () => $svc->clockIn($tzUser, ['source' => 'portal'])));
$recEarly = AttendanceRecord::withoutGlobalScopes()->where('user_id', $tzUser->id)->latest('id')->first();
$out['timezone']['punch_21_30_utc_as_istanbul_00_30'] = [
    'test_now_utc' => '2026-08-05 21:30:00 UTC (= 00:30 TR 6 Aug)',
    'result' => $punchEarly,
    'stored_date' => $recEarly?->getRawOriginal('date') ?? (string) $recEarly?->date,
    'stored_clock_in' => $recEarly?->clock_in,
    'expected_if_europe_istanbul' => '2026-08-06',
    'expected_if_utc_app' => '2026-08-05',
];

// Monthly timesheet boundary sample query
$monthStart = Carbon::parse('2026-08-01', 'UTC')->startOfMonth()->toDateString();
$monthEnd = Carbon::parse('2026-08-01', 'UTC')->endOfMonth()->toDateString();
$out['timezone']['monthly_boundary_utc'] = [
    'startOfMonth' => $monthStart,
    'endOfMonth' => $monthEnd,
    'note' => 'app.timezone=UTC → ay sınırları UTC takvimi; TR gece yarısı kayması mümkün',
];
Carbon::setTestNow();

// ─── 3) MONEY ────────────────────────────────────────────────────
echo "3) money...\n";
$moneyCols = DB::select("
    SELECT table_name, column_name, data_type, numeric_precision, numeric_scale, udt_name
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND (
        column_name ILIKE '%salary%'
        OR column_name ILIKE '%amount%'
        OR column_name ILIKE '%price%'
        OR column_name ILIKE '%cost%'
        OR column_name ILIKE '%balance%'
        OR column_name ILIKE '%bonus%'
        OR column_name ILIKE '%deduction%'
        OR column_name ILIKE '%expense%'
        OR column_name = 'total'
      )
    ORDER BY table_name, column_name
");
$floatHits = array_values(array_filter($moneyCols, fn ($c) => in_array($c->data_type, ['real', 'double precision', 'float'], true) || in_array($c->udt_name, ['float4', 'float8'], true)));
$out['money']['columns'] = $moneyCols;
$out['money']['float_double_hits'] = $floatHits;
$out['money']['rounding_notes'] = [
    'SalaryRecordService' => 'round((float)$amount, 2) on write',
    'SalaryReviewService' => 'round proposed/increase_percent to 2',
    'SalaryBandService' => 'round(ratio, 4)',
    'LeaveCalculationService accrual' => 'float getMonthlyAccrual + PHP += on decimal columns (no BCMath)',
    'Expense' => 'check ExpenseClaim amount column type in list',
];

// ─── 4) TURKISH ──────────────────────────────────────────────────
echo "4) turkish...\n";
$dbCollation = DB::selectOne('SELECT datcollate, datctype FROM pg_database WHERE datname = current_database()');
$out['turkish']['database_collation'] = $dbCollation;
CompanyContext::run($tzCompany->id, function () use ($tzCompany, &$out) {
    foreach (['Çakır', 'Öz', 'Şahin', 'Uçar', 'Ünal', 'İstanbul', 'ismail', 'İSMAİL'] as $i => $name) {
        Employee::create([
            'company_id' => $tzCompany->id,
            'employee_code' => 'TR-'.$i.'-'.uniqid(),
            'full_name' => $name,
            'status' => 'active',
        ]);
    }
});

$ilikeIstanbul = DB::select('SELECT full_name FROM employees WHERE company_id = ? AND full_name ILIKE ? AND deleted_at IS NULL', [$tzCompany->id, '%istanbul%']);
$ilikeIsmail = DB::select('SELECT full_name FROM employees WHERE company_id = ? AND full_name ILIKE ? AND deleted_at IS NULL', [$tzCompany->id, '%ismail%']);
$lowerIstanbul = DB::select('SELECT full_name FROM employees WHERE company_id = ? AND lower(full_name) LIKE lower(?) AND deleted_at IS NULL', [$tzCompany->id, '%istanbul%']);
$sorted = DB::select("SELECT full_name FROM employees WHERE company_id = ? AND full_name IN ('Çakır','Öz','Şahin','Uçar','Ünal') AND deleted_at IS NULL ORDER BY full_name ASC", [$tzCompany->id]);
$icuSorted = catchResult(fn () => DB::select("SELECT full_name FROM employees WHERE company_id = ? AND full_name IN ('Çakır','Öz','Şahin','Uçar','Ünal') AND deleted_at IS NULL ORDER BY full_name COLLATE \"tr-TR-x-icu\" ASC", [$tzCompany->id]));

$out['turkish']['ilike_istanbul'] = array_map(fn ($r) => $r->full_name, $ilikeIstanbul);
$out['turkish']['ilike_ismail'] = array_map(fn ($r) => $r->full_name, $ilikeIsmail);
$out['turkish']['lower_like_istanbul'] = array_map(fn ($r) => $r->full_name, $lowerIstanbul);
$out['turkish']['order_by_default'] = array_map(fn ($r) => $r->full_name, $sorted);
$out['turkish']['order_by_tr_icu'] = $icuSorted;
$out['turkish']['expected_tr_alpha'] = ['Çakır', 'Öz', 'Şahin', 'Uçar', 'Ünal'];

// ─── 5) FILES ────────────────────────────────────────────────────
echo "5) files...\n";
$privateRoot = storage_path('app/private');
$publicRoot = storage_path('app/public');
$out['files']['disks'] = [
    'default' => config('filesystems.default'),
    'private_root' => $privateRoot,
    'public_root' => $publicRoot,
    'env_filesystem_disk' => env('FILESYSTEM_DISK'),
    's3_configurable' => 'AWS_* + FILESYSTEM_DISK=s3',
];

$scanDir = function (string $root) {
    if (! is_dir($root)) {
        return [];
    }
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $files[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        }
    }

    return $files;
};
$privateFiles = $scanDir($privateRoot);
$publicFiles = $scanDir($publicRoot);

$dbPaths = [];
foreach (['employee_documents' => 'file_path', 'documents' => 'file_path', 'document_versions' => 'file_path'] as $table => $col) {
    if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
        foreach (DB::table($table)->whereNotNull($col)->pluck($col) as $p) {
            $dbPaths[] = ltrim(str_replace('\\', '/', (string) $p), '/');
        }
    }
}
$dbPathSet = array_flip($dbPaths);
$orphanPrivate = array_values(array_filter($privateFiles, fn ($p) => ! isset($dbPathSet[$p]) && ! str_starts_with($p, '.gitignore')));
$orphanPublic = array_values(array_filter($publicFiles, fn ($p) => ! isset($dbPathSet[$p]) && ! str_contains($p, '.gitignore')));

// Also check dobedan_hr storage? We're on testing — scan host container storage only
$out['files']['private_file_count'] = count($privateFiles);
$out['files']['public_file_count'] = count($publicFiles);
$out['files']['db_path_count'] = count($dbPaths);
$out['files']['orphan_private_count'] = count($orphanPrivate);
$out['files']['orphan_public_count'] = count($orphanPublic);
$out['files']['orphan_private_sample'] = array_slice($orphanPrivate, 0, 30);
$out['files']['orphan_public_sample'] = array_slice($orphanPublic, 0, 30);
$out['files']['employee_document_destroy'] = 'EmployeeDocumentController::destroy Storage::disk(private)->delete then soft/hard delete model';
$out['files']['employee_destroy'] = 'EmployeeController::destroy only soft-deletes employee — does NOT delete document files';

// Extra: scan dobedan_hr DB paths vs same disk (prod-like data)
config(['database.connections.pgsql.database' => 'dobedan_hr']);
DB::purge('pgsql');
DB::reconnect('pgsql');
$prodPaths = [];
foreach (['employee_documents' => 'file_path', 'documents' => 'file_path', 'document_versions' => 'file_path'] as $table => $col) {
    if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
        foreach (DB::table($table)->whereNotNull($col)->pluck($col) as $p) {
            $prodPaths[] = ltrim(str_replace('\\', '/', (string) $p), '/');
        }
    }
}
$prodSet = array_flip($prodPaths);
$orphanVsProd = array_values(array_filter(array_merge($privateFiles, $publicFiles), function ($p) use ($prodSet) {
    return ! isset($prodSet[$p]) && ! str_contains($p, '.gitignore');
}));
$out['files']['dobedan_hr_db_path_count'] = count($prodPaths);
$out['files']['orphan_vs_dobedan_hr_count'] = count($orphanVsProd);
$out['files']['orphan_vs_dobedan_hr_sample'] = array_slice($orphanVsProd, 0, 40);

// switch back to testing
config(['database.connections.pgsql.database' => 'dobedan_hr_testing']);
DB::purge('pgsql');
DB::reconnect('pgsql');

// ─── 6) QUEUE ────────────────────────────────────────────────────
echo "6) queue...\n";
$hasFailed = Schema::hasTable('failed_jobs');
$hasJobs = Schema::hasTable('jobs');
$out['queue']['tables'] = ['jobs' => $hasJobs, 'failed_jobs' => $hasFailed];
$out['queue']['config'] = [
    'default' => config('queue.default'),
    'redis_retry_after' => config('queue.connections.redis.retry_after'),
    'database_retry_after' => config('queue.connections.database.retry_after'),
    'failed_driver' => config('queue.failed.driver'),
];

// Intentional fail via sync-style: dispatch and run
DB::table('failed_jobs')->where('payload', 'like', '%DenetimBoomJob%')->delete();
class DenetimBoomJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable;
    use \Illuminate\Foundation\Bus\Dispatchable;
    use \Illuminate\Queue\InteractsWithQueue;
    use \Illuminate\Queue\SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('DENETIM intentional failure');
    }
}

// Use database queue for controllable fail
config(['queue.default' => 'database']);
DenetimBoomJob::dispatch();
$jobRow = DB::table('jobs')->orderByDesc('id')->first();
$out['queue']['dispatched_job_id'] = $jobRow->id ?? null;

// Process one job
Artisan::call('queue:work', ['--once' => true, '--queue' => 'default', '--tries' => 1]);
$failCount = DB::table('failed_jobs')->where('exception', 'like', '%DENETIM intentional%')->count();
$failed = DB::table('failed_jobs')->where('exception', 'like', '%DENETIM intentional%')->orderByDesc('id')->first();
$out['queue']['failed_jobs_after_boom'] = $failCount;
$out['queue']['failed_sample_uuid'] = $failed->uuid ?? null;

$retry = null;
if ($failed) {
    $retry = catchResult(fn () => Artisan::call('queue:retry', ['id' => [$failed->uuid]]));
    // work again — will fail again
    Artisan::call('queue:work', ['--once' => true, '--tries' => 1]);
}
$out['queue']['retry_result'] = $retry;
$out['queue']['failed_after_retry'] = DB::table('failed_jobs')->where('exception', 'like', '%DENETIM intentional%')->count();
$out['queue']['accrual_transaction'] = [
    'LeaveAccrualBatchService' => 'per-company try/catch — bir firma patlasa diğerleri devam',
    'LeaveCalculationService::processMonthlyAccruals' => 'DB::transaction YOK — her balance ayrı save; yarım iş kalabilir',
];

// ─── 7) SOFT DELETE + UNIQUE ─────────────────────────────────────
echo "7) soft unique...\n";
$suCo = Company::factory()->create(['status' => CompanyStatus::Active, 'name' => 'DENETIM Unique']);
CompanyContext::run($suCo->id, function () use ($suCo, $uid, &$out) {
    $e1 = Employee::create([
        'company_id' => $suCo->id,
        'employee_code' => 'SICIL-DUP',
        'national_id' => '99988776655',
        'full_name' => 'Silinecek',
        'status' => 'active',
    ]);
    $e1->delete();
    $reCode = catchResult(fn () => Employee::create([
        'company_id' => $suCo->id,
        'employee_code' => 'SICIL-DUP',
        'full_name' => 'Yeni',
        'status' => 'active',
    ]));
    $reTckn = catchResult(fn () => Employee::create([
        'company_id' => $suCo->id,
        'employee_code' => 'SICIL-OTHER',
        'national_id' => '99988776655',
        'full_name' => 'Yeni TCKN',
        'status' => 'active',
    ]));

    $mail = "soft-unique-{$uid}@example.com";
    $u1 = User::factory()->create(['home_company_id' => $suCo->id, 'email' => $mail]);
    $u1->delete();
    $reEmail = catchResult(fn () => User::factory()->create(['home_company_id' => $suCo->id, 'email' => $mail]));

    $b1 = Branch::create(['company_id' => $suCo->id, 'name' => 'B1', 'code' => 'BR-DUP', 'is_active' => true]);
    $b1->delete();
    $reBranch = catchResult(fn () => Branch::create(['company_id' => $suCo->id, 'name' => 'B2', 'code' => 'BR-DUP', 'is_active' => true]));

    $p1 = Position::create(['company_id' => $suCo->id, 'code' => 'POS-DUP', 'name' => 'P1', 'is_active' => true, 'is_system' => false]);
    $p1->delete();
    $rePos = catchResult(fn () => Position::create(['company_id' => $suCo->id, 'code' => 'POS-DUP', 'name' => 'P2', 'is_active' => true, 'is_system' => false]));

    $out['soft_unique'] = [
        'employee_code_after_soft_delete' => $reCode,
        'national_id_note' => 'employees.national_id üzerinde unique YOK — ikinci kayıt '.($reTckn['ok'] ? 'AÇILABİLDİ' : 'engellendi'),
        'national_id_recreate' => $reTckn,
        'user_email_after_soft_delete' => $reEmail,
        'branch_code_after_soft_delete' => $reBranch,
        'position_code_after_soft_delete' => $rePos,
        'unique_indexes' => [
            'employees' => '(company_id, employee_code) — soft-deleted satırlar unique’i bloke eder',
            'users' => 'email unique global — soft-deleted bloke eder',
            'branches' => '(company_id, code)',
            'positions' => '(company_id, code)',
            'national_id' => 'unique yok',
        ],
    ];
});

// ─── 8) EMAIL ────────────────────────────────────────────────────
echo "8) email...\n";
$out['email'] = [
    'User_implements_MustVerifyEmail' => is_subclass_of(User::class, Illuminate\Contracts\Auth\MustVerifyEmail::class),
    'register_sets_email_verified_at' => 'AuthController::register User::create — email_verified_at set edilmiyor (null kalır)',
    'register_returns_token_immediately' => true,
    'verified_middleware_on_api' => 'genelde yok — login email_verified kontrolü?',
    'mailer' => config('mail.default'),
    'mail_from' => config('mail.from'),
    'smtp_host' => config('mail.mailers.smtp.host'),
    'invitation_sets_verified' => 'InvitationService email_verified_at ??= now()',
];

// Check login for verified
$loginCode = file_get_contents(app_path('Http/Controllers/Api/V1/AuthController.php'));
$out['email']['login_checks_verified'] = str_contains($loginCode, 'hasVerifiedEmail') || str_contains($loginCode, 'email_verified');
$out['email']['sendEmailVerificationNotification_usage'] = substr_count($loginCode, 'sendEmailVerificationNotification');

$out['meta']['finished_at'] = now()->toIso8601String();

$path = base_path('../docs/denetim-raw.json');
// backend/../docs
$path = dirname(base_path()).'/docs/denetim-raw.json';
if (! is_dir(dirname($path))) {
    // inside container path may be /var/www/html = backend
    $path = storage_path('logs/denetim-raw.json');
}
// Prefer repo docs via /var/www/html parent — in docker WORKDIR is often /var/www/html = backend
$candidates = [
    '/var/www/html/../docs/denetim-raw.json',
    dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'denetim-raw.json',
    storage_path('logs/denetim-raw.json'),
];
$written = null;
foreach ($candidates as $c) {
    $dir = dirname($c);
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (@file_put_contents($c, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        $written = $c;
        break;
    }
}
echo "WROTE={$written}\n";
echo json_encode(['cascade_keys' => array_keys($out['cascade']), 'tz_dates' => [
    $out['timezone']['punch_20_30_utc_as_istanbul_23_30']['stored_date'] ?? null,
    $out['timezone']['punch_21_30_utc_as_istanbul_00_30']['stored_date'] ?? null,
]], JSON_UNESCAPED_UNICODE)."\n";
