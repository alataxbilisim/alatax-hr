<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\DestructionCandidateStatus;
use App\Enums\JobPositionStatus;
use App\Enums\RetentionStrategy;
use App\Enums\RetentionTriggerEvent;
use App\Enums\UserType;
use App\Jobs\ExecuteDestructionApprovalJob;
use App\Models\Company;
use App\Models\DestructionCandidate;
use App\Models\DestructionLog;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\LegalHold;
use App\Models\RetentionDecision;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Kvkk\Retention\DestructionEngine;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D2c — Saklama politikası + imha + hukuki tutma + ihlal.
 * İmha yalnız bu testlerin ürettiği fixture üzerinde çalışır.
 */
class KvkkD2cTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'd2c-firma-a',
        ]);
        $this->otherCompany = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'd2c-firma-b',
        ]);
        $this->admin = User::factory()->create([
            'home_company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();
    }

    public function test_legal_hold_skips_candidate_and_blocks_destroy(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $user = User::factory()->create(['home_company_id' => $this->company->id, 'name' => 'Hold Person']);
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'terminated',
            'termination_date' => now()->subYears(15)->toDateString(),
            'national_id' => '12345678901',
            'department_id' => null,
        ]);

        LegalHold::query()->create([
            'company_id' => $this->company->id,
            'subject_type' => 'former_employee',
            'subject_id' => $emp->id,
            'reason' => 'Dava dosyası',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
            'active' => true,
        ]);

        $policy = $this->makeFormerEmployeePolicy();

        $engine = app(DestructionEngine::class);
        $result = $engine->scan((int) $this->company->id);

        $this->assertGreaterThanOrEqual(1, $result['skipped_hold']);
        $cand = DestructionCandidate::query()
            ->where('company_id', $this->company->id)
            ->where('subject_id', $emp->id)
            ->first();
        $this->assertNotNull($cand);
        $this->assertSame(DestructionCandidateStatus::SkippedLegalHold, $cand->status);

        // Onay denemesi engellenir
        $this->postJson('/api/v1/kvkk/destruction/approve', [
            'candidate_ids' => [$cand->id],
            'dry_run_confirmed' => true,
        ])->assertStatus(422);

        $emp->refresh();
        $this->assertSame('12345678901', $emp->national_id);
        $this->assertSame($policy->id, $cand->retention_policy_id);
    }

    public function test_job_without_approval_cannot_execute(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Onay kaydı olmadan imha çalıştırılamaz.');

        $engine = app(DestructionEngine::class);
        $engine->assertCanExecute(null);
    }

    public function test_dry_run_does_not_change_data(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'name' => 'DryRun User',
            'email' => 'dryrun@example.com',
        ]);
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'terminated',
            'termination_date' => now()->subYears(12)->toDateString(),
            'national_id' => '99887766554',
            'iban' => 'TR000000000000000000000000',
        ]);

        $this->makeFormerEmployeePolicy();
        $engine = app(DestructionEngine::class);
        $engine->scan((int) $this->company->id);

        $cand = DestructionCandidate::query()
            ->where('subject_id', $emp->id)
            ->where('status', DestructionCandidateStatus::Pending->value)
            ->firstOrFail();

        $beforeEmp = $emp->fresh()->only(['national_id', 'iban', 'department_id', 'gender', 'status']);
        $beforeUser = $user->fresh()->only(['name', 'email']);

        $preview = $engine->dryRun($cand);

        $this->assertTrue($preview['unchanged_proof']['identical']);
        $this->assertSame($beforeEmp, $emp->fresh()->only(['national_id', 'iban', 'department_id', 'gender', 'status']));
        $this->assertSame($beforeUser, $user->fresh()->only(['name', 'email']));
    }

    public function test_anonymize_masks_identity_keeps_stats_and_fk(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $user = User::factory()->create([
            'home_company_id' => $this->company->id,
            'name' => 'Ayrilan Personel',
            'email' => 'ayrilan@example.com',
        ]);
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'terminated',
            'termination_date' => now()->subYears(12)->toDateString(),
            'national_id' => '11122233344',
            'iban' => 'TR111111111111111111111111',
            'gender' => 'female',
            'hire_date' => '2015-03-01',
        ]);

        $terminatedCountBefore = Employee::query()
            ->where('company_id', $this->company->id)
            ->where('status', 'terminated')
            ->count();

        $this->makeFormerEmployeePolicy();
        $engine = app(DestructionEngine::class);
        $engine->scan((int) $this->company->id);
        $cand = DestructionCandidate::query()->where('subject_id', $emp->id)->where('status', 'pending')->firstOrFail();

        $engine->dryRun($cand);
        $approval = $engine->approve((int) $this->company->id, $this->admin, [$cand->id], true, 'test');
        (new ExecuteDestructionApprovalJob($approval->id))->handle($engine);

        $emp->refresh();
        $user->refresh();

        $this->assertNull($emp->national_id);
        $this->assertNull($emp->iban);
        $this->assertSame('female', $emp->gender);
        $this->assertSame('terminated', $emp->status);
        $this->assertNotNull($emp->hire_date);
        $this->assertStringStartsWith('Anonim-', $user->name);
        $this->assertSame($user->id, $emp->user_id); // FK kırılmadı

        $terminatedCountAfter = Employee::query()
            ->where('company_id', $this->company->id)
            ->where('status', 'terminated')
            ->count();
        $this->assertSame($terminatedCountBefore, $terminatedCountAfter);

        $this->assertDatabaseHas('destruction_logs', [
            'company_id' => $this->company->id,
            'subject_id' => $emp->id,
            'outcome' => 'success',
            'dry_run' => false,
        ]);
    }

    public function test_destruction_logs_append_only(): void
    {
        $log = DestructionLog::query()->create([
            'company_id' => $this->company->id,
            'subject_type' => 'former_employee',
            'subject_id' => 1,
            'strategy' => 'anonymize',
            'rows_affected' => 1,
            'outcome' => 'success',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $log->update(['rows_affected' => 99]);
    }

    public function test_destruction_logs_cannot_be_deleted(): void
    {
        $log = DestructionLog::query()->create([
            'company_id' => $this->company->id,
            'subject_type' => 'former_employee',
            'subject_id' => 2,
            'strategy' => 'anonymize',
            'rows_affected' => 1,
            'outcome' => 'success',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_retention_months_below_legal_min_returns_422(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $this->postJson('/api/v1/kvkk/retention-policies', [
            'name' => 'Kısa politika',
            'data_category' => 'identity',
            'subject_type' => 'former_employee',
            'trigger_event' => 'isten_ayrilma',
            'retention_months' => 1,
            'strategy' => 'anonymize',
        ])->assertStatus(422)
            ->assertJsonFragment(['Yasal asgari 6.']);
    }

    public function test_breach_72h_overdue_flag(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $res = $this->postJson('/api/v1/kvkk/breaches', [
            'detected_at' => now()->subHours(80)->toIso8601String(),
            'description' => 'Test ihlali açıklaması yeterince uzun',
            'affected_categories' => ['identity'],
            'affected_subject_count' => 3,
            'severity' => 'high',
        ])->assertCreated();

        $id = $res->json('data.id');
        $list = $this->getJson('/api/v1/kvkk/breaches')->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $id);
        $this->assertTrue((bool) $row['kvkk_deadline_overdue']);
    }

    public function test_rejected_candidate_and_former_employee_e2e(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        // Reddedilen aday
        $position = JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Test Pozisyon',
            'slug' => 'test-pozisyon-d2c',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'published_at' => now(),
        ]);
        $app = JobApplication::create([
            'company_id' => $this->company->id,
            'job_position_id' => $position->id,
            'first_name' => 'Aday',
            'last_name' => 'Red',
            'email' => 'aday@example.com',
            'status' => 'rejected',
            'consent_kvkk' => true,
        ]);
        JobApplication::query()->where('id', $app->id)->update(['updated_at' => now()->subMonths(8)]);

        RetentionPolicy::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Aday test',
            'data_category' => 'cv_recruitment',
            'subject_type' => 'candidate',
            'trigger_event' => RetentionTriggerEvent::BasvuruReddi,
            'retention_months' => 6,
            'strategy' => RetentionStrategy::Anonymize,
            'active' => true,
            'requires_approval' => true,
        ]);

        $user = User::factory()->create(['home_company_id' => $this->company->id, 'name' => 'Eski']);
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'terminated',
            'termination_date' => now()->subYears(11)->toDateString(),
            'national_id' => '55566677788',
        ]);
        $this->makeFormerEmployeePolicy();

        Artisan::call('kvkk:scan-retention', ['--company' => $this->company->id]);

        $this->assertDatabaseHas('destruction_candidates', [
            'company_id' => $this->company->id,
            'subject_type' => 'candidate',
            'subject_id' => $app->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('destruction_candidates', [
            'company_id' => $this->company->id,
            'subject_type' => 'former_employee',
            'subject_id' => $emp->id,
            'status' => 'pending',
        ]);
    }

    public function test_defer_requires_reason_and_reappears_on_scan(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $user = User::factory()->create(['home_company_id' => $this->company->id]);
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'terminated',
            'termination_date' => now()->subYears(12)->toDateString(),
        ]);
        $this->makeFormerEmployeePolicy();
        $engine = app(DestructionEngine::class);
        $engine->scan((int) $this->company->id);
        $cand = DestructionCandidate::query()->where('subject_id', $emp->id)->firstOrFail();

        $this->postJson("/api/v1/kvkk/destruction/candidates/{$cand->id}/decide", [
            'decision' => 'defer',
            'defer_until' => now()->addMonth()->toDateString(),
        ])->assertStatus(422);

        $this->postJson("/api/v1/kvkk/destruction/candidates/{$cand->id}/decide", [
            'decision' => 'defer',
            'reason' => 'Hukuki inceleme devam ediyor',
            'defer_until' => now()->addMonth()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseHas('retention_decisions', [
            'destruction_candidate_id' => $cand->id,
            'decision' => 'defer',
        ]);

        $decision = RetentionDecision::query()->where('destruction_candidate_id', $cand->id)->firstOrFail();
        $this->expectException(LogicException::class);
        $decision->update(['reason' => 'hack']);
    }

    public function test_retention_decisions_cannot_delete(): void
    {
        $d = RetentionDecision::query()->create([
            'company_id' => $this->company->id,
            'subject_type' => 'former_employee',
            'subject_id' => 9,
            'decision' => 'exclude',
            'reason' => 'test',
            'decided_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $d->delete();
    }

    public function test_tenant_isolation_on_destruction_candidates(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->makeFormerEmployeePolicy();
        $user = User::factory()->create(['home_company_id' => $this->company->id]);
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'terminated',
            'termination_date' => now()->subYears(12)->toDateString(),
        ]);
        app(DestructionEngine::class)->scan((int) $this->company->id);
        $cand = DestructionCandidate::query()->where('subject_id', $emp->id)->firstOrFail();

        $otherAdmin = User::factory()->create([
            'home_company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);
        Sanctum::actingAs($otherAdmin->fresh());

        $this->postJson("/api/v1/kvkk/destruction/candidates/{$cand->id}/dry-run")->assertNotFound();
    }

    public function test_seed_drafts_are_inactive(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $this->postJson('/api/v1/kvkk/retention-policies/seed-drafts')->assertOk();
        $this->assertTrue(
            RetentionPolicy::query()
                ->where('company_id', $this->company->id)
                ->where('is_system_draft', true)
                ->where('active', true)
                ->doesntExist()
        );
    }

    private function makeFormerEmployeePolicy(): RetentionPolicy
    {
        return RetentionPolicy::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Ayrılan personel test',
            'data_category' => 'identity',
            'subject_type' => 'former_employee',
            'trigger_event' => RetentionTriggerEvent::IstenAyrilma,
            'retention_months' => 6,
            'strategy' => RetentionStrategy::Anonymize,
            'active' => true,
            'requires_approval' => true,
        ]);
    }
}
