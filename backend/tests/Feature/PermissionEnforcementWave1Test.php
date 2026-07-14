<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Dalga 1: activity-logs + attendance açık kapı kapanışı.
 */
class PermissionEnforcementWave1Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'management.audit_logs.view',
            'management.audit_logs.export',
            'timesheet.attendance.view',
            'timesheet.attendance.create',
            'timesheet.attendance.edit',
            'timesheet.attendance.approve',
            'management.*',
            'timesheet.*',
            'timesheet.attendance.*',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function makeUser(UserType $type, ?Company $company = null): User
    {
        $user = User::factory()->create([
            'type' => $type,
            'company_id' => $type === UserType::SuperAdmin ? null : ($company ?? $this->company)->id,
            'is_active' => true,
        ]);

        if ($type === UserType::CompanyAdmin) {
            return $this->assignSpatieAdminRole($user);
        }

        return $user;
    }

    // ——— activity-logs ———

    public function test_activity_logs_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/activity-logs')->assertStatus(401);
    }

    public function test_activity_logs_user_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));

        $this->getJson('/api/v1/activity-logs')->assertStatus(403);
    }

    public function test_activity_logs_user_with_permission_returns_200(): void
    {
        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('management.audit_logs.view');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/activity-logs')->assertStatus(200);
    }

    public function test_activity_logs_user_with_module_wildcard_returns_200(): void
    {
        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('management.*');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/activity-logs')->assertStatus(200);
    }

    public function test_activity_logs_super_admin_bypass_returns_200(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::SuperAdmin));

        $this->getJson('/api/v1/activity-logs')->assertStatus(200);
    }

    public function test_activity_logs_company_admin_with_admin_role_returns_200(): void
    {
        $admin = $this->makeUser(UserType::CompanyAdmin);
        $this->assertTrue($admin->hasRole('admin'));
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/activity-logs')->assertStatus(200);
    }

    public function test_activity_logs_tenant_isolation(): void
    {
        ActivityLog::create([
            'company_id' => $this->company->id,
            'user_id' => null,
            'action' => 'test',
            'description' => 'own company',
            'is_successful' => true,
        ]);
        ActivityLog::create([
            'company_id' => $this->otherCompany->id,
            'user_id' => null,
            'action' => 'test',
            'description' => 'other company',
            'is_successful' => true,
        ]);

        $user = $this->makeUser(UserType::User, $this->company);
        $user->givePermissionTo('management.audit_logs.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/activity-logs');
        $response->assertStatus(200);

        $descriptions = collect($response->json('data'))->pluck('description');
        $this->assertTrue($descriptions->contains('own company'));
        $this->assertFalse($descriptions->contains('other company'));
    }

    // ——— attendance ———

    public function test_attendance_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/attendance')->assertStatus(401);
    }

    public function test_attendance_user_without_permission_returns_403(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::User));

        $this->getJson('/api/v1/attendance')->assertStatus(403);
    }

    public function test_attendance_user_with_permission_returns_200(): void
    {
        $user = $this->makeUser(UserType::User);
        $user->givePermissionTo('timesheet.attendance.view');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/attendance')->assertStatus(200);
    }

    public function test_attendance_approve_requires_approve_permission(): void
    {
        $viewer = $this->makeUser(UserType::User);
        $viewer->givePermissionTo('timesheet.attendance.view');
        Sanctum::actingAs($viewer);

        $record = AttendanceRecord::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $viewer->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $this->postJson("/api/v1/attendance/{$record->id}/approve")->assertStatus(403);

        // DataScope: company_admin (company) onaylayabilir; salt permission + own scope yetmez
        $approver = $this->makeUser(UserType::CompanyAdmin);
        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/attendance/{$record->id}/approve")->assertStatus(200);
    }

    public function test_attendance_super_admin_and_company_admin_with_admin_role(): void
    {
        Sanctum::actingAs($this->makeUser(UserType::SuperAdmin));
        $this->getJson('/api/v1/attendance')->assertStatus(200);

        $admin = $this->makeUser(UserType::CompanyAdmin);
        $this->assertTrue($admin->hasRole('admin'));
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/attendance')->assertStatus(200);
    }

    public function test_attendance_tenant_isolation(): void
    {
        $ownUser = $this->makeUser(UserType::User, $this->company);
        $otherUser = $this->makeUser(UserType::User, $this->otherCompany);

        $own = AttendanceRecord::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $ownUser->id,
            'date' => now()->toDateString(),
        ]);
        AttendanceRecord::factory()->create([
            'company_id' => $this->otherCompany->id,
            'user_id' => $otherUser->id,
            'date' => now()->toDateString(),
        ]);

        $ownUser->givePermissionTo('timesheet.attendance.view');
        Sanctum::actingAs($ownUser);

        $response = $this->getJson('/api/v1/attendance');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }
}
