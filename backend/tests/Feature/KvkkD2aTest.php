<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\KvkkConsentType;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\ConsentRecord;
use App\Models\DataProcessingActivity;
use App\Models\Employee;
use App\Models\PrivacyNotice;
use App\Models\User;
use App\Services\Kvkk\DataProcessingActivityService;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D2a — Veri envanteri + aydınlatma versiyonlama + rıza kayıtları.
 */
class KvkkD2aTest extends TestCase
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
            'slug' => 'kvkk-firma-a',
        ]);
        $this->otherCompany = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'slug' => 'kvkk-firma-b',
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
        $this->getJson('/api/v1/kvkk/activities')->assertUnauthorized();
        $this->getJson('/api/v1/kvkk/notices')->assertUnauthorized();
        $this->getJson('/api/v1/kvkk/consents')->assertUnauthorized();
    }

    public function test_unauthorized_role_gets_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/kvkk/activities')->assertForbidden();
    }

    public function test_inventory_seed_idempotent_crud_and_tenant_isolation(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $svc = app(DataProcessingActivityService::class);
        $svc->ensureDefaultsForCompany((int) $this->company->id);
        $count1 = DataProcessingActivity::query()->where('company_id', $this->company->id)->count();
        $svc->ensureDefaultsForCompany((int) $this->company->id);
        $this->assertSame(
            $count1,
            DataProcessingActivity::query()->where('company_id', $this->company->id)->count()
        );
        $this->assertGreaterThanOrEqual(5, $count1);

        $list = $this->getJson('/api/v1/kvkk/activities')->assertOk()->json('data');
        $this->assertNotEmpty($list);
        foreach ($list as $row) {
            $this->assertSame($this->company->id, (int) $row['company_id']);
            $this->assertFalse((bool) ($row['transfer_abroad'] ?? true));
        }

        $created = $this->postJson('/api/v1/kvkk/activities', [
            'key' => 'custom_visitor',
            'name' => 'Ziyaretçi kaydı',
            'data_categories' => ['identity', 'contact'],
            'legal_basis' => 'legitimate_interest',
            'data_subject_group' => 'visitor',
            'transfer_abroad' => false,
        ])->assertCreated()->json('data');

        DataProcessingActivity::query()->whereKey($created['id'])->update([
            'purpose' => 'Firma özelleştirdi — ezilmesin',
        ]);
        $svc->ensureDefaultsForCompany((int) $this->company->id);
        $this->assertSame(
            'Firma özelleştirdi — ezilmesin',
            DataProcessingActivity::query()->find($created['id'])?->purpose
        );

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);
        Sanctum::actingAs($otherAdmin->fresh());
        $otherList = $this->getJson('/api/v1/kvkk/activities')->assertOk()->json('data');
        $ids = collect($otherList)->pluck('id');
        $this->assertFalse($ids->contains($created['id']));

        Sanctum::actingAs($this->admin->fresh());
        $this->get('/api/v1/kvkk/activities/export')->assertOk();
    }

    public function test_published_notice_immutable_and_single_active(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $draft = $this->postJson('/api/v1/kvkk/notices', [
            'audience' => 'employee',
            'title' => 'Aydınlatma v1',
            'body' => 'Metin v1 — hukuki içerik firma',
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/kvkk/notices/'.$draft['id'].'/publish')->assertOk();

        $this->putJson('/api/v1/kvkk/notices/'.$draft['id'], [
            'title' => 'Hack',
        ])->assertStatus(422);

        $draft2 = $this->postJson('/api/v1/kvkk/notices', [
            'audience' => 'employee',
            'title' => 'Aydınlatma v2',
            'body' => 'Metin v2',
        ])->assertCreated()->json('data');
        $this->assertSame(2, (int) $draft2['version']);

        $this->postJson('/api/v1/kvkk/notices/'.$draft2['id'].'/publish')->assertOk();

        $activeCount = PrivacyNotice::query()
            ->where('company_id', $this->company->id)
            ->where('audience', 'employee')
            ->where('is_active', true)
            ->count();
        $this->assertSame(1, $activeCount);
        $this->assertFalse(PrivacyNotice::query()->find($draft['id'])->is_active);
        $this->assertTrue(PrivacyNotice::query()->find($draft2['id'])->is_active);
    }

    public function test_consent_types_separate_withdraw_keeps_row_and_public_apply(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $notice = $this->postJson('/api/v1/kvkk/notices', [
            'audience' => 'candidate',
            'title' => 'Aday aydınlatma',
            'body' => 'Aday metni',
        ])->assertCreated()->json('data');
        $this->postJson('/api/v1/kvkk/notices/'.$notice['id'].'/publish')->assertOk();

        // Aydınlatma ve açık rıza ayrı
        $ack = $this->postJson('/api/v1/kvkk/consents', [
            'subject_type' => 'employee',
            'subject_id' => $this->admin->id,
            'notice_id' => null,
            'consent_type' => KvkkConsentType::NoticeRead->value,
            'granted' => true,
            'source' => 'admin',
        ])->assertCreated()->json('data');

        $explicit = $this->postJson('/api/v1/kvkk/consents', [
            'subject_type' => 'employee',
            'subject_id' => $this->admin->id,
            'consent_type' => KvkkConsentType::ExplicitSpecial->value,
            'granted' => true,
            'source' => 'admin',
        ])->assertCreated()->json('data');

        $this->assertNotSame($ack['consent_type'], $explicit['consent_type']);

        $this->postJson('/api/v1/kvkk/consents/'.$explicit['id'].'/withdraw')->assertOk();
        $row = ConsentRecord::query()->findOrFail($explicit['id']);
        $this->assertNotNull($row->withdrawn_at);
        $this->assertFalse($row->granted);
        $this->assertDatabaseHas('consent_records', ['id' => $explicit['id']]);

        // Public apply — onaysız 422
        $this->postJson('/api/v1/public/companies/kvkk-firma-a/jobs/yok/apply', [
            'company_slug' => 'kvkk-firma-a',
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'a@example.com',
        ])->assertStatus(422);

        // Tenant: diğer firma aydınlatmasını görmez
        $this->getJson('/api/v1/public/companies/kvkk-firma-b/privacy-notice')
            ->assertOk()
            ->assertJsonPath('data', null);
        $pub = $this->getJson('/api/v1/public/companies/kvkk-firma-a/privacy-notice')->assertOk()->json('data');
        $this->assertSame((int) $notice['id'], (int) $pub['id']);
    }

    public function test_portal_acknowledge_allows_continue_without_grant(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $notice = $this->postJson('/api/v1/kvkk/notices', [
            'audience' => 'employee',
            'title' => 'Personel aydınlatma',
            'body' => 'Portal metni',
        ])->assertCreated()->json('data');
        $this->postJson('/api/v1/kvkk/notices/'.$notice['id'].'/publish')->assertOk();

        $portalUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($portalUser)->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        Sanctum::actingAs($portalUser->fresh());

        $status = $this->getJson('/api/v1/portal/privacy/status')->assertOk()->json('data');
        $this->assertTrue($status['can_continue_without_ack']);
        $this->assertTrue($status['needs_attention']);

        $this->postJson('/api/v1/portal/privacy/acknowledge', [
            'notice_id' => $notice['id'],
            'granted' => false,
        ])->assertOk();

        $this->assertDatabaseHas('consent_records', [
            'subject_id' => $portalUser->id,
            'consent_type' => KvkkConsentType::NoticeRead->value,
            'granted' => false,
        ]);
    }
}
