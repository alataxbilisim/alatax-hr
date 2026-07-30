<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\DataSubjectExportAccessLog;
use App\Models\DataSubjectExportPackage;
use App\Models\DataSubjectRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SettingValue;
use App\Models\User;
use App\Services\Kvkk\PersonalData\PersonalDataCollectorRegistry;
use App\Services\Settings\Settings;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D2b — Veri sahibi talepleri + kişisel veri ihracı.
 */
class KvkkD2bTest extends TestCase
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
            'slug' => 'd2b-firma-a',
        ]);
        $this->otherCompany = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'd2b-firma-b',
        ]);
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/kvkk/data-subject-requests')->assertUnauthorized();
    }

    public function test_unauthorized_gets_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/kvkk/data-subject-requests')->assertForbidden();
    }

    public function test_export_without_identity_verification_returns_422(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
        ]);

        $res = $this->postJson('/api/v1/kvkk/data-subject-requests', [
            'subject_type' => 'employee',
            'subject_id' => $emp->id,
            'applicant_name' => 'Test',
            'contact' => 't@example.com',
            'request_types' => ['tasinabilirlik'],
            'channel' => 'written',
            'identity_verified' => false,
        ])->assertCreated();

        $id = $res->json('data.id');
        $this->postJson("/api/v1/kvkk/data-subject-requests/{$id}/export")
            ->assertStatus(422)
            ->assertJsonFragment(['Kimlik doğrulanmadan ihraç paketi üretilemez.']);
    }

    public function test_package_scope_excludes_other_persons_records(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $userA = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Alice']);
        $userB = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Bob']);
        $empA = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $userA->id]);
        $empB = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $userB->id]);

        $lt = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Yıllık',
            'code' => 'YL',
            'is_active' => true,
            'default_days' => 14,
        ]);
        LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $userA->id,
            'leave_type_id' => $lt->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'status' => 'approved',
        ]);
        LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $userB->id,
            'leave_type_id' => $lt->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'status' => 'approved',
        ]);

        $payload = app(PersonalDataCollectorRegistry::class)->collectAll('employee', (int) $empA->id, (int) $this->company->id);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString((string) $userA->id, (string) $json);
        $this->assertStringNotContainsString('"user_id":'.$userB->id, (string) $json);
        $this->assertStringNotContainsString($userB->name, (string) $json);
    }

    public function test_anonymous_survey_not_in_package(): void
    {
        $collector = app(PersonalDataCollectorRegistry::class)->all()['survey'];
        $sections = $collector->collect('employee', 999999, (int) $this->company->id);
        $this->assertSame([], $sections);
    }

    public function test_due_date_legal_max_30_cannot_extend(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [['key' => 'kvkk.data_subject.response_days', 'value' => 45]],
        ])->assertStatus(422)->assertJsonFragment(['Yasal azami 30.']);

        $this->putJson('/api/v1/settings/values', [
            'scope_type' => 'company',
            'values' => [['key' => 'kvkk.data_subject.response_days', 'value' => 15]],
        ])->assertOk();

        $days = (int) Settings::get('kvkk.data_subject.response_days', ['company_id' => $this->company->id]);
        $this->assertSame(15, $days);

        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
        ]);
        $res = $this->postJson('/api/v1/kvkk/data-subject-requests', [
            'subject_type' => 'employee',
            'subject_id' => $emp->id,
            'applicant_name' => 'X',
            'contact' => 'x@ex.com',
            'request_types' => ['bilgi_talebi'],
            'channel' => 'written',
            'identity_verified' => true,
            'verification_method' => 'id_card',
        ])->assertCreated();

        $due = $res->json('data.due_date');
        $this->assertSame(now()->addDays(15)->toDateString(), $due);
    }

    public function test_download_unauthorized_403_and_expired_410_and_logged(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
        ]);
        $create = $this->postJson('/api/v1/kvkk/data-subject-requests', [
            'subject_type' => 'employee',
            'subject_id' => $emp->id,
            'applicant_name' => 'A',
            'contact' => 'a@ex.com',
            'request_types' => ['tasinabilirlik'],
            'channel' => 'written',
            'identity_verified' => true,
            'verification_method' => 'id_card',
        ])->assertCreated();
        $id = (int) $create->json('data.id');

        $export = $this->postJson("/api/v1/kvkk/data-subject-requests/{$id}/export")->assertOk();
        $uuid = $export->json('data.package.uuid');

        $stranger = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($stranger);
        $this->get("/api/v1/kvkk/data-subject-requests/{$id}/export/{$uuid}/download")
            ->assertForbidden();

        Sanctum::actingAs($this->admin->fresh());
        $this->get("/api/v1/kvkk/data-subject-requests/{$id}/export/{$uuid}/download")
            ->assertOk();
        $this->assertSame(1, DataSubjectExportAccessLog::query()->where('company_id', $this->company->id)->count());

        $pkg = DataSubjectExportPackage::query()->where('uuid', $uuid)->firstOrFail();
        $pkg->forceFill(['expires_at' => now()->subDay()])->save();
        $this->get("/api/v1/kvkk/data-subject-requests/{$id}/export/{$uuid}/download")
            ->assertStatus(410);
    }

    public function test_silme_approve_sets_destruction_pending_without_deleting_data(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $emp = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $user->id]);

        $create = $this->postJson('/api/v1/kvkk/data-subject-requests', [
            'subject_type' => 'employee',
            'subject_id' => $emp->id,
            'applicant_name' => $user->name,
            'contact' => $user->email,
            'request_types' => ['silme'],
            'channel' => 'written',
            'identity_verified' => true,
            'verification_method' => 'id_card',
        ])->assertCreated();
        $id = (int) $create->json('data.id');

        $this->postJson("/api/v1/kvkk/data-subject-requests/{$id}/respond", [
            'template' => 'accept',
            'body' => 'Silme talebiniz kabul edilmiştir.',
        ])->assertOk();

        $row = DataSubjectRequest::query()->findOrFail($id);
        $this->assertTrue($row->destruction_pending);
        $this->assertNotNull($row->destruction_scope);
        $this->assertDatabaseHas('employees', ['id' => $emp->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_tenant_isolation(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
        ]);
        $create = $this->postJson('/api/v1/kvkk/data-subject-requests', [
            'subject_type' => 'employee',
            'subject_id' => $emp->id,
            'applicant_name' => 'A',
            'contact' => 'a@ex.com',
            'request_types' => ['bilgi_talebi'],
            'channel' => 'written',
            'identity_verified' => true,
            'verification_method' => 'manual',
        ])->assertCreated();
        $id = (int) $create->json('data.id');

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);
        Sanctum::actingAs($otherAdmin->fresh());
        $this->getJson("/api/v1/kvkk/data-subject-requests/{$id}")->assertNotFound();
    }

    public function test_portal_creates_verified_request(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/portal/data-subject-requests', [
            'request_types' => ['bilgi_talebi'],
            'description' => 'Portal talebi',
        ])->assertCreated();

        $this->assertTrue($res->json('data.identity_verified'));
        $this->assertSame(now()->addDays(30)->toDateString(), $res->json('data.due_date'));
    }
}
