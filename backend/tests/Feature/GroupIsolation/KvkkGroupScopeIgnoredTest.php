<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\DataSubjectRequestChannel;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectType;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\DataSubjectRequest;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Services\CompanyContextService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * G2 — KVKK yollarında scope=group yok sayılır; satırlar yalnız aktif şirket.
 */
class KvkkGroupScopeIgnoredTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org KVKK G2', 'slug' => 'org-kvkk-g2']);
        $this->companyA = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
            'slug' => 'kvkk-g2-a',
        ]);
        $this->companyB = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
            'slug' => 'kvkk-g2-b',
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assignSpatieAdminRole($this->admin->fresh());
        app(CompanyContextService::class)->ensureMembership($this->admin, (int) $this->companyB->id, false);

        $empA = Employee::factory()->create(['company_id' => $this->companyA->id, 'status' => 'active']);
        $empB = Employee::factory()->create(['company_id' => $this->companyB->id, 'status' => 'active']);

        DataSubjectRequest::query()->create([
            'company_id' => $this->companyA->id,
            'subject_type' => DataSubjectType::Employee,
            'subject_id' => $empA->id,
            'applicant_name' => 'DSR A',
            'contact' => 'a@example.com',
            'request_types' => ['erisim'],
            'description' => 'A',
            'channel' => DataSubjectRequestChannel::Email,
            'status' => DataSubjectRequestStatus::New,
            'identity_verified' => false,
            'due_date' => now()->addDays(10)->toDateString(),
            'created_by' => $this->admin->id,
        ]);
        DataSubjectRequest::query()->create([
            'company_id' => $this->companyB->id,
            'subject_type' => DataSubjectType::Employee,
            'subject_id' => $empB->id,
            'applicant_name' => 'DSR B',
            'contact' => 'b@example.com',
            'request_types' => ['erisim'],
            'description' => 'B',
            'channel' => DataSubjectRequestChannel::Email,
            'status' => DataSubjectRequestStatus::New,
            'identity_verified' => false,
            'due_date' => now()->addDays(10)->toDateString(),
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_kvkk_list_ignores_scope_group_stays_active_company(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->getJson(
            '/api/v1/kvkk/data-subject-requests?scope=group',
            ['X-Company-Id' => (string) $this->companyA->id]
        )->assertOk();

        $names = collect($res->json('data'))->pluck('applicant_name')->all();
        $this->assertContains('DSR A', $names);
        $this->assertNotContains('DSR B', $names);
    }
}
