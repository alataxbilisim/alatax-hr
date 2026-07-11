<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Document;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Dalga 2: recruitment + leaves + documents permission enforcement.
 */
class PermissionEnforcementWave2Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private Company $unlicensedCompany;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'recruitment.positions.view',
            'recruitment.positions.create',
            'recruitment.applications.view',
            'recruitment.cv_pool.view',
            'recruitment.forms.view',
            'recruitment.interviews.view',
            'recruitment.reports.view',
            'recruitment.*',
            'leaves.types.view',
            'leaves.requests.view',
            'leaves.requests.approve',
            'leaves.calendar.view',
            'leaves.balances.view',
            'leaves.*',
            'documents.categories.view',
            'documents.list.view',
            'documents.reports.view',
            'documents.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->unlicensedCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'job-applications');
        $this->enableModule($this->company, 'leave-management');
        $this->enableModule($this->company, 'document-management');

        $this->enableModule($this->otherCompany, 'job-applications');
        $this->enableModule($this->otherCompany, 'leave-management');
        $this->enableModule($this->otherCompany, 'document-management');
        // unlicensedCompany: modül yok
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug,
                'is_core' => false,
                'is_active' => true,
            ]
        );

        $company->modules()->syncWithoutDetaching([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);
    }

    private function makeUser(UserType $type, ?Company $company = null): User
    {
        return User::factory()->create([
            'type' => $type,
            'company_id' => $type === UserType::SuperAdmin ? null : ($company ?? $this->company)->id,
            'is_active' => true,
        ]);
    }

    private function calendarQuery(): string
    {
        return http_build_query([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * @param  mixed  $payload
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function extractIds(mixed $payload): \Illuminate\Support\Collection
    {
        if (! is_array($payload)) {
            return collect();
        }

        // LengthAwarePaginator JSON: { data: [...], current_page, ... }
        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            return collect($payload['data'])->pluck('id');
        }

        if (array_is_list($payload)) {
            return collect($payload)->pluck('id');
        }

        return collect();
    }

    // ——— public/jobs (permission YOK — korundu) ———

    public function test_public_jobs_unauthenticated_returns_200(): void
    {
        $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/jobs')
            ->assertStatus(200);
    }

    // ——— recruitment ———

    public function test_recruitment_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/recruitment/positions')->assertStatus(401);
    }

    public function test_recruitment_unlicensed_company_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::CompanyAdmin, $this->unlicensedCompany));

        $this->getJson('/api/v1/recruitment/positions')->assertStatus(403);
    }

    public function test_recruitment_user_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));

        $this->getJson('/api/v1/recruitment/positions')->assertStatus(403);
    }

    public function test_recruitment_user_with_permission_returns_200(): void
    {
        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('recruitment.positions.view');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/recruitment/positions')->assertStatus(200);
    }

    public function test_recruitment_super_admin_and_company_admin_bypass(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::SuperAdmin));
        $this->getJson('/api/v1/recruitment/positions')->assertStatus(200);

        Sanctum::actingAs($this->makeUser(UserType::CompanyAdmin));
        $this->getJson('/api/v1/recruitment/positions')->assertStatus(200);
    }

    public function test_recruitment_tenant_isolation(): void
    {
        $own = JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Own Position',
            'slug' => 'own-position-'.uniqid(),
            'status' => JobPositionStatus::Active,
        ]);
        JobPosition::create([
            'company_id' => $this->otherCompany->id,
            'title' => 'Other Position',
            'slug' => 'other-position-'.uniqid(),
            'status' => JobPositionStatus::Active,
        ]);

        $user = $this->makeUser(UserType::User, $this->company);
        $user->givePermissionTo('recruitment.positions.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/recruitment/positions');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    // ——— leaves ———

    public function test_leaves_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/leaves/types')->assertStatus(401);
    }

    public function test_leaves_unlicensed_company_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::CompanyAdmin, $this->unlicensedCompany));

        $this->getJson('/api/v1/leaves/types')->assertStatus(403);
    }

    public function test_leaves_user_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));

        $this->getJson('/api/v1/leaves/types')->assertStatus(403);
    }

    public function test_leaves_user_with_permission_returns_200(): void
    {
        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('leaves.types.view');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/leaves/types')->assertStatus(200);
    }

    public function test_leaves_approve_requires_approve_permission(): void
    {
        $leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN',
            'is_active' => true,
        ]);

        $employee = $this->makeUser(UserType::User);
        $leaveRequest = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'total_days' => 2,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $viewer = $this->makeUser(UserType::User);
        $viewer->givePermissionTo('leaves.requests.view');
        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/leaves/requests/{$leaveRequest->id}/approve")->assertStatus(403);

        $approver = $this->makeUser(UserType::User);
        $approver->givePermissionTo('leaves.requests.approve');
        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/leaves/requests/{$leaveRequest->id}/approve")->assertStatus(200);
    }

    public function test_leaves_super_admin_and_company_admin_bypass(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::SuperAdmin));
        $this->getJson('/api/v1/leaves/types')->assertStatus(200);

        Sanctum::actingAs($this->makeUser(UserType::CompanyAdmin));
        $this->getJson('/api/v1/leaves/types')->assertStatus(200);
    }

    public function test_leaves_tenant_isolation(): void
    {
        $own = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Own Leave',
            'code' => 'OWN',
            'is_active' => true,
        ]);
        $other = LeaveType::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Leave',
            'code' => 'OTH',
            'is_active' => true,
        ]);

        $user = $this->makeUser(UserType::User, $this->company);
        $user->givePermissionTo('leaves.types.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/leaves/types');
        $response->assertStatus(200);

        $ids = $this->extractIds($response->json('data'));
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_leaves_calendar_requires_permission(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));
        $this->getJson('/api/v1/leaves/calendar?'.$this->calendarQuery())->assertStatus(403);

        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('leaves.calendar.view');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/leaves/calendar?'.$this->calendarQuery())->assertStatus(200);
    }

    // ——— documents ———

    public function test_documents_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/documents/categories')->assertStatus(401);
    }

    public function test_documents_unlicensed_company_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::CompanyAdmin, $this->unlicensedCompany));

        $this->getJson('/api/v1/documents/categories')->assertStatus(403);
    }

    public function test_documents_user_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));

        $this->getJson('/api/v1/documents/categories')->assertStatus(403);
        $this->getJson('/api/v1/documents')->assertStatus(403);
    }

    public function test_documents_user_with_permission_returns_200(): void
    {
        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('documents.categories.view');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/documents/categories')->assertStatus(200);

        $listUser = $this->makeUser(UserType::User);
        $listUser->givePermissionTo('documents.list.view');
        Sanctum::actingAs($listUser);

        $this->getJson('/api/v1/documents')->assertStatus(200);
    }

    public function test_documents_super_admin_and_company_admin_bypass(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::SuperAdmin));
        $this->getJson('/api/v1/documents/categories')->assertStatus(200);

        Sanctum::actingAs($this->makeUser(UserType::CompanyAdmin));
        $this->getJson('/api/v1/documents')->assertStatus(200);
    }

    public function test_documents_tenant_isolation(): void
    {
        $own = Document::create([
            'company_id' => $this->company->id,
            'name' => 'Own Doc',
            'file_name' => 'own.pdf',
            'file_path' => 'docs/own.pdf',
            'file_size' => 100,
            'file_type' => 'application/pdf',
        ]);
        Document::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Doc',
            'file_name' => 'other.pdf',
            'file_path' => 'docs/other.pdf',
            'file_size' => 100,
            'file_type' => 'application/pdf',
        ]);

        $user = $this->makeUser(UserType::User, $this->company);
        $user->givePermissionTo('documents.list.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/documents');
        $response->assertStatus(200);

        $ids = $this->extractIds($response->json('data'));
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }
}
