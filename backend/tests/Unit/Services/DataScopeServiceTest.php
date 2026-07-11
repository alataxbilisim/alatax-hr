<?php

namespace Tests\Unit\Services;

use App\Enums\CompanyStatus;
use App\Enums\DataScopeLevel;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\DataScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DataScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    private DataScopeService $service;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->service = app(DataScopeService::class);
        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function makeUserWithRole(string $roleName, ?string $dataScopeOverride = null): User
    {
        $role = Role::findOrCreate($roleName, 'sanctum');
        if ($dataScopeOverride !== null) {
            $role->data_scope = $dataScopeOverride;
            $role->save();
        }

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_resolve_uses_config_defaults(): void
    {
        $this->assertSame(DataScopeLevel::Company, $this->service->resolve($this->makeUserWithRole('hr_manager')));
        $this->assertSame(DataScopeLevel::Department, $this->service->resolve($this->makeUserWithRole('hr_specialist')));
        $this->assertSame(DataScopeLevel::Team, $this->service->resolve($this->makeUserWithRole('manager')));
        $this->assertSame(DataScopeLevel::Own, $this->service->resolve($this->makeUserWithRole('employee')));
        $this->assertSame(DataScopeLevel::Company, $this->service->resolve($this->makeUserWithRole('admin')));
    }

    public function test_resolve_role_column_overrides_config(): void
    {
        $user = $this->makeUserWithRole('manager', 'company');
        $this->assertSame(DataScopeLevel::Company, $this->service->resolve($user));
    }

    public function test_resolve_widest_wins_with_multiple_roles(): void
    {
        $user = $this->makeUserWithRole('employee');
        $manager = Role::findOrCreate('manager', 'sanctum');
        $user->assignRole($manager);

        $this->assertSame(DataScopeLevel::Team, $this->service->resolve($user->fresh()));
    }

    public function test_resolve_without_roles_defaults_to_own(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);

        $this->assertSame(DataScopeLevel::Own, $this->service->resolve($user));
    }

    public function test_scope_for_user_own_filters_to_self(): void
    {
        $actor = $this->makeUserWithRole('employee');
        $other = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $leaveType = $this->leaveType();

        $own = $this->leave($actor, $leaveType);
        $this->leave($other, $leaveType);

        $ids = $this->service->scopeForUser(LeaveRequest::query(), $actor)->pluck('id');

        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_scope_for_user_team_includes_subordinates(): void
    {
        $managerUser = $this->makeUserWithRole('manager');
        $subUser = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $otherUser = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        $managerEmp = Employee::factory()->forUser($managerUser)->create();
        Employee::factory()->forUser($subUser)->create(['manager_id' => $managerEmp->id]);
        Employee::factory()->forUser($otherUser)->create();

        $leaveType = $this->leaveType();
        $teamLeave = $this->leave($subUser, $leaveType);
        $otherLeave = $this->leave($otherUser, $leaveType);
        $ownLeave = $this->leave($managerUser, $leaveType);

        $ids = $this->service->scopeForUser(LeaveRequest::query(), $managerUser)->pluck('id');

        $this->assertTrue($ids->contains($teamLeave->id));
        $this->assertTrue($ids->contains($ownLeave->id));
        $this->assertFalse($ids->contains($otherLeave->id));
    }

    public function test_scope_for_user_department_filters_by_department(): void
    {
        $deptA = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept A',
            'code' => 'A-'.uniqid(),
            'is_active' => true,
        ]);
        $deptB = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept B',
            'code' => 'B-'.uniqid(),
            'is_active' => true,
        ]);

        $specialist = $this->makeUserWithRole('hr_specialist');
        $sameDept = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $otherDept = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);

        Employee::factory()->forUser($specialist)->create(['department_id' => $deptA->id]);
        Employee::factory()->forUser($sameDept)->create(['department_id' => $deptA->id]);
        Employee::factory()->forUser($otherDept)->create(['department_id' => $deptB->id]);

        $leaveType = $this->leaveType();
        $inScope = $this->leave($sameDept, $leaveType);
        $outScope = $this->leave($otherDept, $leaveType);

        $ids = $this->service->scopeForUser(LeaveRequest::query(), $specialist)->pluck('id');

        $this->assertTrue($ids->contains($inScope->id));
        $this->assertFalse($ids->contains($outScope->id));
    }

    public function test_scope_for_user_company_has_no_extra_filter(): void
    {
        $hr = $this->makeUserWithRole('hr_manager');
        $a = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $b = User::factory()->create(['company_id' => $this->company->id, 'type' => UserType::User]);
        $leaveType = $this->leaveType();

        $la = $this->leave($a, $leaveType);
        $lb = $this->leave($b, $leaveType);

        $ids = $this->service->scopeForUser(LeaveRequest::query(), $hr)->pluck('id');

        $this->assertTrue($ids->contains($la->id));
        $this->assertTrue($ids->contains($lb->id));
    }

    private function leaveType(): LeaveType
    {
        return LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN-'.uniqid(),
            'is_active' => true,
            'default_days' => 14,
        ]);
    }

    private function leave(User $user, LeaveType $type): LeaveRequest
    {
        return LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'total_days' => 2,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }
}
