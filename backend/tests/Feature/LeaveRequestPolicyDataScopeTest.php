<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 2 Dalga 1: LeaveRequest Policy + DataScope.
 */
class LeaveRequestPolicyDataScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'leaves.requests.view',
            'leaves.requests.create',
            'leaves.requests.approve',
            'leaves.requests.edit',
            'leaves.requests.delete',
            'leaves.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'leave-management');
        $this->enableModule($this->otherCompany, 'leave-management');

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN',
            'is_active' => true,
            'default_days' => 14,
        ]);
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

    private function userWithRole(string $role, ?Company $company = null): User
    {
        $user = User::factory()->create([
            'company_id' => ($company ?? $this->company)->id,
            'type' => UserType::User,
        ]);
        $user->assignRole($role);
        $user->givePermissionTo([
            'leaves.requests.view',
            'leaves.requests.approve',
            'leaves.requests.create',
        ]);

        return $user;
    }

    private function leaveFor(User $user, ?Company $company = null): LeaveRequest
    {
        return LeaveRequest::create([
            'company_id' => ($company ?? $this->company)->id,
            'user_id' => $user->id,
            'leave_type_id' => $company && $company->id !== $this->company->id
                ? LeaveType::create([
                    'company_id' => $company->id,
                    'name' => 'Other',
                    'code' => 'OT-'.uniqid(),
                    'is_active' => true,
                ])->id
                : $this->leaveType->id,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'total_days' => 2,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }

    public function test_manager_sees_and_approves_team_leave(): void
    {
        $manager = $this->userWithRole('manager');
        $sub = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $managerEmp = Employee::factory()->forUser($manager)->create();
        Employee::factory()->forUser($sub)->create(['manager_id' => $managerEmp->id]);

        $leave = $this->leaveFor($sub);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/leaves/requests/{$leave->id}")->assertStatus(200);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);

        $list = $this->getJson('/api/v1/leaves/requests');
        $list->assertStatus(200);
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($leave->id));
    }

    public function test_manager_cannot_see_or_approve_other_team_leave(): void
    {
        $managerA = $this->userWithRole('manager');
        $managerB = $this->userWithRole('manager');
        $subB = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        Employee::factory()->forUser($managerA)->create();
        $empB = Employee::factory()->forUser($managerB)->create();
        Employee::factory()->forUser($subB)->create(['manager_id' => $empB->id]);

        $leave = $this->leaveFor($subB);

        Sanctum::actingAs($managerA);

        $list = $this->getJson('/api/v1/leaves/requests');
        $list->assertStatus(200);
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($leave->id));

        $this->getJson("/api/v1/leaves/requests/{$leave->id}")->assertStatus(403);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(403);
    }

    public function test_hr_manager_sees_and_approves_all_company_leaves(): void
    {
        $hr = $this->userWithRole('hr_manager');
        $a = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $b = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $leaveA = $this->leaveFor($a);
        $leaveB = $this->leaveFor($b);

        Sanctum::actingAs($hr);

        $list = $this->getJson('/api/v1/leaves/requests');
        $list->assertStatus(200);
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($leaveA->id));
        $this->assertTrue($ids->contains($leaveB->id));

        $this->postJson("/api/v1/leaves/requests/{$leaveA->id}/approve")->assertStatus(200);
    }

    public function test_employee_sees_own_but_not_others(): void
    {
        $employee = $this->userWithRole('employee');
        $other = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $own = $this->leaveFor($employee);
        $theirs = $this->leaveFor($other);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/leaves/requests/{$own->id}")->assertStatus(200);
        $this->getJson("/api/v1/leaves/requests/{$theirs->id}")->assertStatus(403);

        $list = $this->getJson('/api/v1/leaves/requests');
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_company_admin_with_admin_role_sees_and_approves_all(): void
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin);
        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $leave = $this->leaveFor($employee);

        Sanctum::actingAs($admin->fresh());

        $this->getJson("/api/v1/leaves/requests/{$leave->id}")->assertStatus(200);
        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(200);
    }

    public function test_tenant_isolation_other_company_leave_not_visible(): void
    {
        $hr = $this->userWithRole('hr_manager');
        $otherUser = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::User,
        ]);
        $otherLeave = $this->leaveFor($otherUser, $this->otherCompany);

        Sanctum::actingAs($hr);

        $list = $this->getJson('/api/v1/leaves/requests');
        $ids = collect($list->json('data.data') ?? $list->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($otherLeave->id));

        // Route model binding + BelongsToCompany → 404
        $this->getJson("/api/v1/leaves/requests/{$otherLeave->id}")->assertStatus(404);
    }

    public function test_permission_alone_without_team_cannot_approve_legacy_closed(): void
    {
        // Rol yok → own scope; approve permission olsa bile başkasını onaylayamaz
        $approver = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $approver->givePermissionTo(['leaves.requests.view', 'leaves.requests.approve']);

        $employee = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $leave = $this->leaveFor($employee);

        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/leaves/requests/{$leave->id}/approve")->assertStatus(403);
    }
}
