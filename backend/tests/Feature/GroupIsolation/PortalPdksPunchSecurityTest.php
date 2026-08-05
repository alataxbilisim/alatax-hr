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
 * Tur5 — Portal/QR punch: personel auth user_id'den çözülür; istek employee_id yok sayılır.
 * Öncelik: home ile eşleşen personel → yoksa auth kullanıcısının aktif personeli.
 */
class PortalPdksPunchSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private Branch $branchA;

    private Branch $branchB;

    private User $adminA;

    private User $userA;

    private Employee $employeeBForeign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org Punch', 'slug' => 'org-punch-t5']);
        $this->companyA = Company::factory()->create([
            'name' => 'Punch A',
            'slug' => 'punch-a-t5',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'Punch B',
            'slug' => 'punch-b-t5',
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

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'name' => 'Merkez A',
            'code' => 'PA',
            'is_active' => true,
        ]);
        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'name' => 'Merkez B',
            'code' => 'PB',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->adminA->fresh());
        $this->adminA->givePermissionTo(['timesheet.kiosk.view', 'timesheet.attendance.view']);

        $this->userA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        app(CompanyContextService::class)->ensureMembership($this->userA, (int) $this->companyA->id, true);
        app(CompanyContextService::class)->ensureMembership($this->userA, (int) $this->companyB->id, false);

        Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);

        $userB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        app(CompanyContextService::class)->ensureMembership($userB, (int) $this->companyB->id, true);
        $this->employeeBForeign = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $userB->id,
            'branch_id' => $this->branchB->id,
            'status' => 'active',
        ]);
    }

    public function test_portal_clock_in_ignores_foreign_employee_id_in_request(): void
    {
        Sanctum::actingAs($this->userA->fresh());

        $this->postJson('/api/v1/portal/timesheet/clock-in', [
            'employee_id' => $this->employeeBForeign->id,
            'user_id' => $this->employeeBForeign->user_id,
            'latitude' => 41.0,
            'longitude' => 29.0,
        ])->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->userA->id,
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('attendance_records', [
            'user_id' => $this->employeeBForeign->user_id,
        ]);
        $this->assertDatabaseMissing('attendance_records', [
            'company_id' => $this->companyB->id,
            'user_id' => $this->userA->id,
        ]);
    }

    public function test_portal_qr_rejects_other_company_token_for_company_a_user(): void
    {
        $adminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($adminB->fresh());
        $adminB->givePermissionTo(['timesheet.kiosk.view']);

        $tokenB = CompanyContext::run($this->companyB->id, function () use ($adminB) {
            return app(AttendanceKioskTokenService::class)->issue(
                (int) $this->companyB->id,
                (int) $this->branchB->id,
                (int) $adminB->id
            )['token'];
        });

        Sanctum::actingAs($this->userA->fresh());
        $this->postJson('/api/v1/portal/timesheet/qr-scan', [
            'token' => $tokenB,
            'employee_id' => $this->employeeBForeign->id,
        ])->assertStatus(422);

        $this->assertSame(0, AttendanceRecord::query()->where('user_id', $this->userA->id)->count());
        $this->assertSame(0, AttendanceRecord::query()->where('user_id', $this->employeeBForeign->user_id)->count());
    }

    public function test_portal_clock_in_uses_employee_company_when_home_differs(): void
    {
        // Home A, personel kaydı yalnız B — öncelik: home eşleşmesi yok → personel şirketi B
        $mismatch = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        app(CompanyContextService::class)->ensureMembership($mismatch, (int) $this->companyA->id, true);
        app(CompanyContextService::class)->ensureMembership($mismatch, (int) $this->companyB->id, false);
        Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $mismatch->id,
            'branch_id' => $this->branchB->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($mismatch->fresh());
        $this->postJson('/api/v1/portal/timesheet/clock-in', [
            'latitude' => 41.0,
            'longitude' => 29.0,
        ])->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $mismatch->id,
            'company_id' => $this->companyB->id,
        ]);
        $this->assertDatabaseMissing('attendance_records', [
            'user_id' => $mismatch->id,
            'company_id' => $this->companyA->id,
        ]);
    }
}
