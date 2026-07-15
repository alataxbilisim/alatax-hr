<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Module;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * PDKS-2 Z1: Vardiya CRUD + atama + DataScope.
 */
class PdksShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $manager;

    private User $teamMember;

    private User $outsider;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['admin', 'manager', 'employee'] as $role) {
            Role::findOrCreate($role, 'sanctum');
        }

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $mod = Module::firstOrCreate(
            ['slug' => 'timesheet'],
            ['name' => 'Puantaj', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $mod->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Operasyon',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo([
            'timesheet.shifts.view',
            'timesheet.shifts.create',
            'timesheet.shifts.edit',
            'timesheet.shifts.delete',
        ]);

        $this->manager = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');
        $this->manager->givePermissionTo([
            'timesheet.shifts.view',
            'timesheet.shifts.create',
            'timesheet.shifts.edit',
        ]);
        $managerEmp = Employee::factory()->forUser($this->manager)->create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
        ]);

        $this->teamMember = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->teamMember->assignRole('employee');
        Employee::factory()->forUser($this->teamMember)->create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'manager_id' => $managerEmp->id,
        ]);

        $this->outsider = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->outsider->assignRole('employee');
        Employee::factory()->forUser($this->outsider)->create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            // farklı manager — team dışında
        ]);

        $this->shift = Shift::create([
            'company_id' => $this->company->id,
            'name' => 'Sabah',
            'code' => 'SAB',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_shift(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/shifts', [
            'name' => 'Akşam',
            'code' => 'AKS',
            'start_time' => '16:00',
            'end_time' => '00:00',
            'break_duration_minutes' => 30,
            'is_night_shift' => true,
        ])->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Akşam');
    }

    public function test_unauthenticated_shift_list_returns_401(): void
    {
        $this->getJson('/api/v1/shifts')->assertStatus(401);
    }

    public function test_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->teamMember);

        $this->getJson('/api/v1/shifts')->assertStatus(403);
    }

    public function test_manager_can_assign_to_team_member(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/employee-shifts', [
            'user_id' => $this->teamMember->id,
            'shift_id' => $this->shift->id,
            'date' => now()->toDateString(),
        ])->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_manager_cannot_assign_outside_team(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/employee-shifts', [
            'user_id' => $this->outsider->id,
            'shift_id' => $this->shift->id,
            'date' => now()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_bulk_assign_denies_all_out_of_scope(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/employee-shifts/bulk', [
            'shift_id' => $this->shift->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'user_ids' => [$this->outsider->id],
        ])->assertStatus(403);
    }

    public function test_admin_can_bulk_assign(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/employee-shifts/bulk', [
            'shift_id' => $this->shift->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'user_ids' => [$this->teamMember->id, $this->outsider->id],
        ])->assertOk()
            ->assertJsonPath('data.user_count', 2);
    }
}
