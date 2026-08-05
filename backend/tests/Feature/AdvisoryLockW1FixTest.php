<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Exceptions\AdvisoryLockTimeoutException;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use App\Services\Approval\ApprovalFlowEngine;
use App\Services\WorkflowService;
use App\Support\PostgresAdvisoryLock;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use RuntimeException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * W1-fix: advisory lock ömrü, tenant anahtar kapsamı, timeout, exception sonrası serbest kalma.
 */
class AdvisoryLockW1FixTest extends TestCase
{
    use RefreshDatabase;

    public function test_xact_lock_released_after_exception_allows_retry(): void
    {
        try {
            DB::transaction(function (): void {
                PostgresAdvisoryLock::transactionScoped(10, 'approval_instance', 99, '2s');
                throw new RuntimeException('forced-mid-lock');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('forced-mid-lock', $e->getMessage());
        }

        // İç TX rollback sonrası aynı anahtar yeniden alınabilmeli (bloklama/timeout yok).
        // Not: RefreshDatabase dış TX hâlâ açıkken xact lock pg_locks'ta görünebilir —
        // asıl sızıntı kontrolü TestCase::tearDown (dış TX rollback sonrası).
        DB::transaction(function (): void {
            PostgresAdvisoryLock::transactionScoped(10, 'approval_instance', 99, '2s');
        });

        $this->assertTrue(true);
    }

    public function test_tenant_keys_isolate_same_entity_id_across_companies(): void
    {
        $a = PostgresAdvisoryLock::keys(1, 'approval_instance', 42);
        $b = PostgresAdvisoryLock::keys(2, 'approval_instance', 42);

        $this->assertSame(1, $a[0]);
        $this->assertSame(2, $b[0]);
        $this->assertNotSame($a[0], $b[0], 'company_id anahtarın parçası olmalı (tenant)');
        $this->assertSame($a[1], $b[1], 'aynı scope:id → aynı k2');
    }

    public function test_lock_timeout_throws_advisory_lock_timeout_exception(): void
    {
        [$k1, $k2] = PostgresAdvisoryLock::keys(7, 'approval_instance', 55);

        $cfg = config('database.connections.'.config('database.default'));
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg['host'],
            $cfg['port'] ?? '5432',
            $cfg['database']
        );
        $holder = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $holder->exec('BEGIN');
        $stmt = $holder->prepare('SELECT pg_advisory_xact_lock(?, ?)');
        $stmt->execute([$k1, $k2]);
        $stmt->fetchAll();
        $stmt->closeCursor();

        try {
            DB::transaction(function () use ($k1, $k2): void {
                PostgresAdvisoryLock::transactionScoped(7, 'approval_instance', 55, '200ms');
            });
            $this->fail('AdvisoryLockTimeoutException bekleniyordu');
        } catch (AdvisoryLockTimeoutException $e) {
            $response = $e->render(request());
            $this->assertSame(423, $response->getStatusCode());
        } finally {
            $holder->exec('ROLLBACK');
            $holder = null;
        }
    }

    public function test_approve_exception_does_not_leave_blocking_lock(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $module = Module::firstOrCreate(
            ['slug' => 'leave-management'],
            ['name' => 'leave-management', 'is_core' => false, 'is_active' => true]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $approver = User::factory()->create([
            'home_company_id' => $company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $requester = User::factory()->create([
            'home_company_id' => $company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        Employee::factory()->forUser($requester)->create(['company_id' => $company->id]);

        $leaveType = LeaveType::create([
            'company_id' => $company->id,
            'name' => 'W1-fix',
            'code' => 'W1F',
            'is_active' => true,
            'default_days' => 20,
        ]);
        LeaveBalance::create([
            'company_id' => $company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'total_days' => 20,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        $wf = ApprovalWorkflow::create([
            'company_id' => $company->id,
            'name' => 'W1-fix WF',
            'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'created_by' => $approver->id,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $wf->id,
            'step_order' => 1,
            'name' => 'Onay',
            'approver_type' => ApprovalStep::APPROVER_USER,
            'specific_user_id' => $approver->id,
            'is_required' => true,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'total_days' => 2,
            'reason' => 'lock test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $record = app(WorkflowService::class)->startWorkflow($leave, [
            'total_days' => 2,
            'requester_id' => $requester->id,
        ]);
        $this->assertNotNull($record);

        $this->partialMock(ApprovalFlowEngine::class, function ($mock): void {
            $mock->shouldReceive('afterApproval')
                ->once()
                ->andThrow(new RuntimeException('forced-approve-fail'));
        });

        try {
            $record->fresh()->approve('will fail', $approver->id);
            $this->fail('RuntimeException bekleniyordu');
        } catch (RuntimeException $e) {
            $this->assertSame('forced-approve-fail', $e->getMessage());
        }

        // Mock'u kaldır — kilit TX rollback ile düşmüş olmalı
        $this->app->forgetInstance(ApprovalFlowEngine::class);
        Mockery::close();

        $fresh = ApprovalRecord::query()->findOrFail($record->id);
        $this->assertSame(ApprovalRecord::STATUS_PENDING, $fresh->status);

        $ok = $fresh->approve('retry ok', $approver->id);
        $this->assertTrue($ok);

        $status = $leave->fresh()->status;
        $statusValue = is_object($status) && property_exists($status, 'value')
            ? $status->value
            : (string) $status;
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $statusValue);
    }

    public function test_aborted_seed_transaction_does_not_leak_session_lock(): void
    {
        // Eski bug: PermissionSeeder unlock aborted TX içinde → 25P02 + sızıntı.
        // Yan bağlantı kilidi varsayılan backend pid'inde görünmemeli.
        PostgresAdvisoryLock::withSessionLockOnSideConnection(
            PostgresAdvisoryLock::KEY_TESTING_PERMISSION_SEED,
            function (): void {
                try {
                    DB::transaction(function (): void {
                        DB::select('SELECT 1');
                        throw new RuntimeException('seed-boom');
                    });
                } catch (RuntimeException) {
                    //
                }
            }
        );

        $this->assertSame([], PostgresAdvisoryLock::heldByCurrentBackend());
    }
}
