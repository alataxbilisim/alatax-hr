<?php

namespace Tests\Security;

use App\Enums\LeaveRequestStatus;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\KeyResult;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LicensePackage;
use App\Models\Module;
use App\Models\Objective;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOtherCompanyFixture;
use Tests\Concerns\CreatesPortalEmployees;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * Adım 03B S3 — `error($m, null, KOD)` çağrıları 500 (TypeError) dönüyordu; artık doğru kod + ApiResponse zarfı.
 */
class ErrorResponseStatusTest extends TestCase
{
    use CreatesOtherCompanyFixture;
    use CreatesPortalEmployees;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private Company $company;

    private User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->company = $this->createActiveCompany();
        foreach (['leave-management', 'training', 'performance'] as $slug) {
            $module = Module::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_core' => false, 'is_active' => true]);
            $this->company->modules()->syncWithoutDetaching([$module->id => ['is_active' => true, 'activated_at' => now()]]);
        }
        $this->portalUser = $this->portalEmployee($this->company);
    }

    private function assertErrorEnvelope(TestResponse $response, int $status): void
    {
        $response->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors', 'timestamp']);
    }

    public function test_portal_missing_records_return_404(): void
    {
        Sanctum::actingAs($this->portalUser, ['portal:access']);

        $this->assertErrorEnvelope($this->getJson('/api/v1/portal/leaves/999999'), 404);
        $this->assertErrorEnvelope($this->putJson('/api/v1/portal/leaves/999999', ['reason' => 'x']), 404);
        $this->assertErrorEnvelope($this->getJson('/api/v1/portal/announcements/999999'), 404);
        $this->assertErrorEnvelope($this->getJson('/api/v1/portal/training/999999'), 404);
        $this->assertErrorEnvelope($this->getJson('/api/v1/portal/training/certificates/999999'), 404);
        $this->assertErrorEnvelope($this->getJson('/api/v1/portal/performance/reviews/999999'), 404);
        $this->assertErrorEnvelope($this->postJson('/api/v1/portal/performance/reviews/999999/comment', ['comment' => 'x']), 404);
        $this->assertErrorEnvelope($this->getJson('/api/v1/portal/performance/okrs/999999'), 404);
        $this->assertErrorEnvelope($this->putJson('/api/v1/portal/performance/key-results/999999', ['current_value' => 1]), 404);
    }

    public function test_portal_okr_endpoints_work_for_owner(): void
    {
        $objective = Objective::factory()->create(['company_id' => $this->company->id, 'owner_id' => $this->portalUser->id]);
        $keyResult = KeyResult::create([
            'objective_id' => $objective->id,
            'owner_id' => $this->portalUser->id,
            'title' => 'Müşteri memnuniyeti',
            'metric_type' => KeyResult::METRIC_NUMBER,
            'start_value' => 0,
            'target_value' => 10,
            'current_value' => 0,
            'status' => KeyResult::STATUS_NOT_STARTED,
        ]);

        Sanctum::actingAs($this->portalUser, ['portal:access']);

        $this->getJson('/api/v1/portal/performance/okrs')->assertOk()->assertJsonPath('data.0.id', $objective->id);
        $this->getJson("/api/v1/portal/performance/okrs/{$objective->id}")->assertOk();
        $this->putJson("/api/v1/portal/performance/key-results/{$keyResult->id}", ['current_value' => 4, 'note' => 'Ara ölçüm'])
            ->assertOk()
            ->assertJsonPath('data.status', KeyResult::STATUS_ON_TRACK);

        $this->assertDatabaseHas('key_result_updates', [
            'key_result_id' => $keyResult->id,
            'user_id' => $this->portalUser->id,
            'previous_value' => 0,
            'new_value' => 4,
        ]);
    }

    public function test_portal_leave_business_rules_return_422(): void
    {
        $inactive = LeaveType::factory()->create(['company_id' => $this->company->id, 'is_active' => false]);
        $needsDocument = LeaveType::factory()->create(['company_id' => $this->company->id, 'is_active' => true, 'requires_document' => true]);
        $dates = ['start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString()];

        Sanctum::actingAs($this->portalUser, ['portal:access']);

        $this->assertErrorEnvelope($this->postJson('/api/v1/portal/leaves', ['leave_type_id' => $inactive->id] + $dates), 422);
        $this->assertErrorEnvelope($this->postJson('/api/v1/portal/leaves', ['leave_type_id' => $needsDocument->id] + $dates), 422);
    }

    public function test_portal_wrong_current_password_returns_422_with_field_error(): void
    {
        $this->portalUser->forceFill(['password' => Hash::make('Dogru-Sifre-123')])->save();
        Sanctum::actingAs($this->portalUser, ['portal:access']);

        $response = $this->putJson('/api/v1/portal/profile/password', [
            'current_password' => 'Yanlis-Sifre-123',
            'password' => 'Yeni-Sifre-12345',
            'password_confirmation' => 'Yeni-Sifre-12345',
        ]);

        $this->assertErrorEnvelope($response, 422);
        $response->assertJsonPath('errors.current_password.0', 'Mevcut şifre hatalı');
    }

    public function test_panel_leave_approve_of_processed_request_returns_422(): void
    {
        $admin = $this->companyAdminFor($this->company);
        $leave = LeaveRequest::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->portalUser->id,
            'status' => LeaveRequestStatus::Approved,
        ]);

        $this->actingAsInCompany($admin, $this->company);

        $this->assertErrorEnvelope($this->postJson("/api/v1/leaves/requests/{$leave->id}/approve"), 422);
        $this->assertErrorEnvelope($this->postJson("/api/v1/leaves/requests/{$leave->id}/reject", ['reason' => 'x']), 422);
    }

    public function test_super_admin_ledger_and_package_errors(): void
    {
        $superAdmin = $this->superAdminUser();
        $other = $this->otherCompany();
        $transaction = CompanyLedger::factory()->create(['company_id' => $other->id]);
        $package = LicensePackage::factory()->create();
        $this->company->forceFill(['license_package_id' => $package->id])->save();

        Sanctum::actingAs($superAdmin);

        $this->assertErrorEnvelope($this->getJson("/api/v1/admin/companies/{$this->company->id}/ledger/{$transaction->id}"), 404);
        $this->assertErrorEnvelope($this->deleteJson("/api/v1/admin/license-packages/{$package->id}"), 422);
    }
}
