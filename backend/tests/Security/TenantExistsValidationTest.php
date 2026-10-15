<?php

namespace Tests\Security;

use App\Models\Company;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOtherCompanyFixture;
use Tests\Concerns\CreatesPortalEmployees;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * Adım 03B S2 — `company_exists` / `company_member`: başka şirketin kimliği 422, kendi şirketinki geçer.
 */
class TenantExistsValidationTest extends TestCase
{
    use CreatesOtherCompanyFixture;
    use CreatesPortalEmployees;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->company = $this->createActiveCompany();
        $this->admin = $this->companyAdminFor($this->company);
    }

    public function test_department_parent_and_manager_must_belong_to_active_company(): void
    {
        $other = $this->otherCompany();
        $foreignDepartment = Department::factory()->create(['company_id' => $other->id]);
        $foreignUser = $this->userWithPermissions($other);

        $this->actingAsInCompany($this->admin, $this->company);
        $this->postJson('/api/v1/departments', [
            'name' => 'Muhasebe',
            'parent_id' => $foreignDepartment->id,
            'manager_id' => $foreignUser->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['parent_id', 'manager_id']);

        $ownDepartment = Department::factory()->create(['company_id' => $this->company->id]);
        $this->postJson('/api/v1/departments', [
            'name' => 'Muhasebe',
            'parent_id' => $ownDepartment->id,
            'manager_id' => $this->admin->id,
        ])->assertCreated();
    }

    public function test_portal_leave_type_must_belong_to_home_company(): void
    {
        $foreignType = LeaveType::factory()->create(['company_id' => $this->otherCompany()->id, 'is_active' => true]);
        $portalUser = $this->portalEmployee($this->company);

        Sanctum::actingAs($portalUser, ['portal:access']);
        $this->postJson('/api/v1/portal/leaves', [
            'leave_type_id' => $foreignType->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('leave_type_id');
    }
}
