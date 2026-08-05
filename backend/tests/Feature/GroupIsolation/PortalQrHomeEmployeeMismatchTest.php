<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\Timesheet\AttendanceKioskTokenService;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur6 — QR consume, resolvePortalCompanyId ile aynı önceliği kullanmalı.
 * Home A + personel yalnız B → B kiosk QR okutulunca punch B'ye yazılır (422 değil).
 */
class PortalQrHomeEmployeeMismatchTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private Branch $branchB;

    private User $mismatchUser;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org QR Mismatch', 'slug' => 'org-qr-mm-t6']);
        $this->companyA = Company::factory()->create([
            'name' => 'QR Home A',
            'slug' => 'qr-home-a-t6',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'QR Emp B',
            'slug' => 'qr-emp-b-t6',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);

        $mod = Module::firstOrCreate(
            ['slug' => 'timesheet'],
            ['name' => 'Puantaj', 'is_core' => false, 'is_active' => true]
        );
        foreach ([$this->companyA, $this->companyB] as $c) {
            $c->modules()->syncWithoutDetaching([
                $mod->id => ['is_active' => true, 'activated_at' => now()],
            ]);
        }

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'name' => 'Merkez B',
            'code' => 'QMB',
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->adminB->fresh());
        $this->adminB->givePermissionTo(['timesheet.kiosk.view', 'timesheet.attendance.view']);

        $this->mismatchUser = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        app(CompanyContextService::class)->ensureMembership($this->mismatchUser, (int) $this->companyA->id, true);
        app(CompanyContextService::class)->ensureMembership($this->mismatchUser, (int) $this->companyB->id, false);
        Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->mismatchUser->id,
            'branch_id' => $this->branchB->id,
            'status' => 'active',
        ]);
    }

    public function test_portal_qr_punch_uses_employee_company_when_home_differs(): void
    {
        $this->assertSame($this->companyA->id, (int) $this->mismatchUser->company_id);
        $portalCompany = app(CompanyContextService::class)->resolvePortalCompanyId($this->mismatchUser);
        $this->assertSame($this->companyB->id, $portalCompany);

        $token = CompanyContext::run($this->companyB->id, function () {
            return app(AttendanceKioskTokenService::class)->issue(
                (int) $this->companyB->id,
                (int) $this->branchB->id,
                (int) $this->adminB->id
            )['token'];
        });

        Sanctum::actingAs($this->mismatchUser->fresh());
        $this->postJson('/api/v1/portal/timesheet/qr-scan', ['token' => $token])
            ->assertOk();

        $record = AttendanceRecord::query()->where('user_id', $this->mismatchUser->id)->first();
        $this->assertNotNull($record);
        $this->assertSame($this->companyB->id, (int) $record->company_id);
        $this->assertDatabaseMissing('attendance_records', [
            'user_id' => $this->mismatchUser->id,
            'company_id' => $this->companyA->id,
        ]);
    }
}
