<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Announcement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Payslip;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * C5 — Company duyuru + bordro + vardiya portal besleme.
 */
class CompanyGapsC5Test extends TestCase
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

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
    }

    public function test_announcement_crud_publish_audience_and_tenant(): void
    {
        $deptA = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept A',
            'code' => 'DA',
            'is_active' => true,
        ]);
        $deptB = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Dept B',
            'code' => 'DB',
            'is_active' => true,
        ]);

        $userIn = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'in-aud@example.com',
        ]);
        $empIn = Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $userIn->id,
            'employee_code' => 'E-IN',
            'status' => 'active',
            'department_id' => $deptA->id,
        ]);

        $userOut = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'out-aud@example.com',
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $userOut->id,
            'employee_code' => 'E-OUT',
            'status' => 'active',
            'department_id' => $deptB->id,
        ]);

        $this->getJson('/api/v1/announcements')->assertUnauthorized();

        $viewer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/announcements')->assertForbidden();

        Sanctum::actingAs($this->admin->fresh());
        $create = $this->postJson('/api/v1/announcements', [
            'title' => 'Hedefli duyuru',
            'content' => "Merhaba\nDünya",
            'is_for_all' => false,
            'target_departments' => [$deptA->id],
        ])->assertCreated();

        $id = (int) $create->json('data.id');

        $this->postJson("/api/v1/announcements/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('data.notified', 1);

        $this->assertTrue(
            $userIn->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'announcement.published'
            )
        );
        $this->assertFalse(
            $userOut->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'announcement.published'
            )
        );

        Sanctum::actingAs($userIn);
        $portal = $this->getJson('/api/v1/portal/announcements')->assertOk();
        $ids = collect($portal->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));

        Sanctum::actingAs($userOut);
        $portalOut = $this->getJson('/api/v1/portal/announcements')->assertOk();
        $idsOut = collect($portalOut->json('data'))->pluck('id');
        $this->assertFalse($idsOut->contains($id));

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);
        Sanctum::actingAs($otherAdmin->fresh());
        $this->getJson("/api/v1/announcements/{$id}")->assertNotFound();
    }

    public function test_payslip_upload_owner_and_other_access(): void
    {
        Storage::fake('private');

        $owner = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'owner-pay@example.com',
        ]);
        $ownerEmp = Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $owner->id,
            'employee_code' => 'PAY-1',
            'status' => 'active',
        ]);

        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'other-pay@example.com',
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $other->id,
            'employee_code' => 'PAY-2',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->admin->fresh());
        $file = UploadedFile::fake()->create('bordro.pdf', 100, 'application/pdf');
        $created = $this->post('/api/v1/payslips', [
            'employee_id' => $ownerEmp->id,
            'period' => '2026-06',
            'net_salary' => 25000,
            'publish' => true,
            'file' => $file,
        ], ['Accept' => 'application/json'])->assertCreated();

        $payslipId = (int) $created->json('data.id');
        $this->assertDatabaseHas('payslips', [
            'id' => $payslipId,
            'is_published' => true,
            'employee_id' => $ownerEmp->id,
        ]);

        $this->assertTrue(
            $owner->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'payslip.published'
            )
        );

        Sanctum::actingAs($owner);
        $ownerList = $this->getJson('/api/v1/portal/payslips')->assertOk();
        $ownerIds = collect($ownerList->json('data'))->pluck('id');
        $this->assertTrue($ownerIds->contains($payslipId));

        Sanctum::actingAs($other);
        $list = $this->getJson('/api/v1/portal/payslips')->assertOk();
        $ids = collect($list->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($payslipId));
        $this->getJson("/api/v1/portal/payslips/{$payslipId}")->assertNotFound();

        // HR yetkilisi görür
        Sanctum::actingAs($this->admin->fresh());
        $this->getJson("/api/v1/payslips/{$payslipId}")->assertOk();

        // Yetkisiz user company API 403
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/payslips')->assertForbidden();
    }

    public function test_shift_assignment_visible_on_portal(): void
    {
        $employeeUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'shift-portal@example.com',
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $employeeUser->id,
            'employee_code' => 'SH-1',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->admin->fresh());
        $shift = $this->postJson('/api/v1/shifts', [
            'name' => 'Sabah',
            'code' => 'SAB',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_duration_minutes' => 30,
            'is_active' => true,
        ])->assertCreated()->json('data');

        $date = now()->startOfWeek()->toDateString();
        $this->postJson('/api/v1/employee-shifts', [
            'user_id' => $employeeUser->id,
            'shift_id' => $shift['id'],
            'date' => $date,
        ])->assertSuccessful();

        Sanctum::actingAs($employeeUser);
        $portal = $this->getJson('/api/v1/portal/timesheet/shifts?week_start='.$date)
            ->assertOk();

        $weekShifts = collect($portal->json('data.shifts') ?? []);
        $this->assertTrue(
            $weekShifts->contains(fn ($row) => ($row['date'] ?? null) === $date && ($row['shift']['id'] ?? null) == $shift['id'])
        );

        // Yetkisiz shift create
        Sanctum::actingAs($employeeUser);
        $this->postJson('/api/v1/shifts', [
            'name' => 'X',
            'code' => 'X1',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])->assertForbidden();
    }
}
