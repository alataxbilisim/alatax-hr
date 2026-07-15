<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * PDKS-2 Z3: manuel düzeltme + rapor DataScope.
 */
class PdksAttendanceCorrectionReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $teamMember;

    private User $outsider;

    private AttendanceRecord $outsiderRecord;

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
            'name' => 'Ops',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');
        $this->manager->givePermissionTo([
            'timesheet.attendance.view',
            'timesheet.attendance.create',
            'timesheet.attendance.edit',
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
        Employee::factory()->forUser($this->outsider)->create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
        ]);

        $this->outsiderRecord = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->outsider->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => 'present',
            'source' => 'manual',
        ]);
    }

    public function test_manager_can_create_for_team_member(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/attendance', [
            'user_id' => $this->teamMember->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'reason' => 'Eksik kayıt düzeltmesi',
        ])->assertStatus(201);
    }

    public function test_manager_cannot_create_outside_scope(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/attendance', [
            'user_id' => $this->outsider->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'reason' => 'Yetkisiz deneme',
        ])->assertStatus(403);
    }

    public function test_manager_cannot_edit_outside_scope(): void
    {
        Sanctum::actingAs($this->manager);

        $this->putJson('/api/v1/attendance/'.$this->outsiderRecord->id, [
            'clock_in' => '10:00',
            'clock_out' => '18:00',
            'reason' => 'Yetkisiz düzenleme',
        ])->assertStatus(403);
    }

    public function test_update_requires_reason(): void
    {
        Sanctum::actingAs($this->manager);

        $record = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->teamMember->id,
            'date' => now()->subDays(2)->toDateString(),
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => 'present',
            'source' => 'manual',
        ]);

        $this->putJson('/api/v1/attendance/'.$record->id, [
            'clock_in' => '09:15',
            'clock_out' => '18:00',
        ])->assertStatus(422);
    }

    public function test_report_scoped_to_team(): void
    {
        AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->teamMember->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:30',
            'clock_out' => '18:00',
            'status' => 'late',
            'late_minutes' => 30,
            'source' => 'manual',
        ]);

        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/attendance/reports?start_date='.now()->toDateString().'&end_date='.now()->toDateString())
            ->assertOk();

        $rows = $res->json('data.rows');
        $userIds = collect($rows)->pluck('user_id')->all();

        $this->assertContains($this->teamMember->id, $userIds);
        $this->assertNotContains($this->outsider->id, $userIds);
    }
}
