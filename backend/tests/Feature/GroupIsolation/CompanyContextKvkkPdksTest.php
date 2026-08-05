<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\DataSubjectRequestChannel;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectType;
use App\Enums\DestructionCandidateStatus;
use App\Enums\RetentionStrategy;
use App\Enums\RetentionTriggerEvent;
use App\Enums\UserType;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\DataBreach;
use App\Models\DataSubjectRequest;
use App\Models\DestructionCandidate;
use App\Models\Employee;
use App\Models\LegalHold;
use App\Models\Organization;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\Timesheet\AttendanceClockService;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur3 B — P1 KVKK + P2 PDKS: aktif bağlam ≠ home iken doğru şirket.
 */
class CompanyContextKvkkPdksTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org KV', 'slug' => 'org-kv']);
        $this->companyA = Company::factory()->create([
            'name' => 'KV Company A',
            'slug' => 'kv-company-a',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'KV Company B',
            'slug' => 'kv-company-b',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);

        $this->userA = User::factory()->create([
            'home_company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assignSpatieAdminRole($this->userA->fresh());
        app(CompanyContextService::class)->ensureMembership($this->userA, (int) $this->companyB->id, false);
    }

    /** @return array<string, string> */
    private function headers(int $companyId): array
    {
        return ['X-Company-Id' => (string) $companyId];
    }

    public function test_p1_legal_hold_follows_active_context_not_home(): void
    {
        $holdB = LegalHold::query()->create([
            'company_id' => $this->companyB->id,
            'subject_type' => 'employee',
            'subject_id' => 1,
            'reason' => 'Dava B',
            'placed_by' => $this->userA->id,
            'placed_at' => now(),
            'active' => true,
        ]);
        LegalHold::query()->create([
            'company_id' => $this->companyA->id,
            'subject_type' => 'employee',
            'subject_id' => 2,
            'reason' => 'Dava A',
            'placed_by' => $this->userA->id,
            'placed_at' => now(),
            'active' => true,
        ]);

        Sanctum::actingAs($this->userA);

        $listB = $this->getJson('/api/v1/kvkk/legal-holds', $this->headers($this->companyB->id))->assertOk();
        $idsB = collect($listB->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($holdB->id, $idsB);
        $this->assertCount(1, $idsB);

        $create = $this->postJson('/api/v1/kvkk/legal-holds', [
            'subject_type' => 'employee',
            'subject_id' => 99,
            'reason' => 'Aktif B tutma',
        ], $this->headers($this->companyB->id))->assertCreated();
        $this->assertSame($this->companyB->id, (int) $create->json('data.company_id'));

        $this->postJson(
            '/api/v1/kvkk/legal-holds/'.$holdB->id.'/release',
            [],
            $this->headers($this->companyA->id)
        )->assertNotFound();
    }

    public function test_p1_data_breach_follows_active_context_not_home(): void
    {
        $breachB = DataBreach::query()->create([
            'company_id' => $this->companyB->id,
            'detected_at' => now(),
            'description' => 'İhlal B kaydı',
            'severity' => 'medium',
            'status' => 'open',
            'created_by' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA);

        $listB = $this->getJson('/api/v1/kvkk/breaches', $this->headers($this->companyB->id))->assertOk();
        $ids = collect($listB->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($breachB->id, $ids);

        $created = $this->postJson('/api/v1/kvkk/breaches', [
            'detected_at' => now()->toIso8601String(),
            'description' => 'Yeni ihlal aktif B',
            'severity' => 'low',
        ], $this->headers($this->companyB->id))->assertCreated();
        $this->assertSame($this->companyB->id, (int) $created->json('data.company_id'));

        $this->getJson(
            '/api/v1/kvkk/breaches/'.$breachB->id.'/report',
            $this->headers($this->companyA->id)
        )->assertNotFound();
    }

    public function test_p1_retention_policy_follows_active_context_not_home(): void
    {
        $policyB = RetentionPolicy::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Politika B',
            'data_category' => 'identity',
            'subject_type' => 'employee',
            'trigger_event' => RetentionTriggerEvent::IstenAyrilma,
            'retention_months' => 120,
            'strategy' => RetentionStrategy::Anonymize,
            'active' => true,
            'created_by' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA);

        $listB = $this->getJson('/api/v1/kvkk/retention-policies', $this->headers($this->companyB->id))->assertOk();
        $ids = collect($listB->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($policyB->id, $ids);

        $created = $this->postJson('/api/v1/kvkk/retention-policies', [
            'name' => 'Politika aktif B',
            'data_category' => 'contact',
            'subject_type' => 'employee',
            'trigger_event' => RetentionTriggerEvent::KayitTarihi->value,
            'retention_months' => 24,
            'strategy' => 'anonymize',
            'active' => true,
        ], $this->headers($this->companyB->id))->assertCreated();
        $this->assertSame($this->companyB->id, (int) $created->json('data.company_id'));

        $this->putJson(
            '/api/v1/kvkk/retention-policies/'.$policyB->id,
            ['name' => 'hack'],
            $this->headers($this->companyA->id)
        )->assertNotFound();
    }

    public function test_p1_destruction_candidates_follow_active_context_not_home(): void
    {
        $policyB = RetentionPolicy::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'İmha politika B',
            'data_category' => 'identity',
            'subject_type' => 'former_employee',
            'trigger_event' => RetentionTriggerEvent::IstenAyrilma,
            'retention_months' => 12,
            'strategy' => RetentionStrategy::Anonymize,
            'active' => true,
            'created_by' => $this->userA->id,
        ]);
        $candB = DestructionCandidate::query()->create([
            'company_id' => $this->companyB->id,
            'retention_policy_id' => $policyB->id,
            'subject_type' => 'former_employee',
            'subject_id' => 1,
            'data_category' => 'identity',
            'record_count' => 1,
            'due_since' => now()->subYear(),
            'status' => DestructionCandidateStatus::Pending,
            'strategy' => RetentionStrategy::Anonymize,
        ]);

        Sanctum::actingAs($this->userA);

        $summary = $this->getJson('/api/v1/kvkk/destruction/summary', $this->headers($this->companyB->id))->assertOk();
        $this->assertSame(1, (int) $summary->json('data.pending_count'));

        $list = $this->getJson('/api/v1/kvkk/destruction/candidates', $this->headers($this->companyB->id))->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($candB->id, $ids);

        $this->postJson(
            '/api/v1/kvkk/destruction/candidates/'.$candB->id.'/decide',
            ['decision' => 'defer', 'reason' => 'bekle', 'defer_until' => now()->addMonth()->toDateString()],
            $this->headers($this->companyA->id)
        )->assertNotFound();
    }

    public function test_p1_data_subject_download_employee_match_uses_active_context(): void
    {
        // Home A; personel kaydı B'de. contact ≠ email → yalnız employee eşleşmesi yolu.
        $portalUser = User::factory()->create([
            'home_company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
            'email' => 'portal-kv@example.com',
        ]);
        app(CompanyContextService::class)->ensureMembership($portalUser, (int) $this->companyB->id, false);
        $empB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $portalUser->id,
            'status' => 'active',
        ]);

        $row = DataSubjectRequest::query()->create([
            'company_id' => $this->companyB->id,
            'subject_type' => DataSubjectType::Employee,
            'subject_id' => $empB->id,
            'applicant_name' => 'Portal KV',
            'contact' => 'dsr-contact@example.com',
            'request_types' => ['erisim'],
            'description' => 'İndirme testi',
            'channel' => DataSubjectRequestChannel::Portal,
            'status' => DataSubjectRequestStatus::Completed,
            'identity_verified' => true,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $controller = app(\App\Http\Controllers\Api\V1\Kvkk\DataSubjectRequestController::class);
        $method = new \ReflectionMethod($controller, 'canDownload');
        $method->setAccessible(true);

        Sanctum::actingAs($portalUser);

        $okB = CompanyContext::run($this->companyB->id, fn () => $method->invoke($controller, $portalUser, $row));
        $okA = CompanyContext::run($this->companyA->id, fn () => $method->invoke($controller, $portalUser, $row));

        $this->assertTrue($okB, 'Aktif B: employee eşleşmesi getCompanyId ile');
        $this->assertFalse($okA, 'Aktif A: home company_id ile yanlış eşleşme olmamalı');
    }

    public function test_p2_attendance_clock_writes_to_active_company_context(): void
    {
        $this->assertNotSame($this->companyA->id, $this->companyB->id);
        $this->assertSame($this->companyA->id, (int) $this->userA->home_company_id);

        CompanyContext::run($this->companyB->id, function () {
            $clock = app(AttendanceClockService::class);
            $result = $clock->clockIn($this->userA, [
                'method' => 'mobile',
                'source' => AttendanceClockService::SOURCE_PORTAL,
            ]);
            $this->assertSame($this->companyB->id, (int) $result['record']->company_id);
        });

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->userA->id,
            'company_id' => $this->companyB->id,
        ]);
        $this->assertDatabaseMissing('attendance_records', [
            'user_id' => $this->userA->id,
            'company_id' => $this->companyA->id,
            'date' => now()->toDateString(),
        ]);

        // Home A bağlamında (context yok) hâlâ home'a yazar
        CompanyContext::forget();
        AttendanceRecord::query()->where('user_id', $this->userA->id)->delete();
        $home = app(AttendanceClockService::class)->clockIn($this->userA, [
            'method' => 'mobile',
            'source' => AttendanceClockService::SOURCE_PORTAL,
        ]);
        $this->assertSame($this->companyA->id, (int) $home['record']->company_id);
    }
}
