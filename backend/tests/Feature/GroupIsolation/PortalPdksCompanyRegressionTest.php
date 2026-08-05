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
 * Tur4 — Portal puantaj, panel last_company_id'den etkilenmemeli.
 * Personel kaydı A'da; panelde B seçili → portal clock-in / QR → kayıt A'ya.
 */
class PortalPdksCompanyRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private Branch $branchA;

    private User $dualUser;

    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org PDKS', 'slug' => 'org-pdks-t4']);
        $this->companyA = Company::factory()->create([
            'name' => 'PDKS A',
            'slug' => 'pdks-a-t4',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'PDKS B',
            'slug' => 'pdks-b-t4',
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
            'code' => 'MA',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create([
            'home_company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->adminA->fresh());
        $this->adminA->givePermissionTo(['timesheet.kiosk.view', 'timesheet.attendance.view']);

        // Panel + portal: home A, membership B, personel kaydı yalnız A
        $this->dualUser = User::factory()->create([
            'home_company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        app(CompanyContextService::class)->ensureMembership($this->dualUser, (int) $this->companyA->id, true);
        app(CompanyContextService::class)->ensureMembership($this->dualUser, (int) $this->companyB->id, false);
        Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->dualUser->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);
    }

    public function test_portal_clock_in_uses_employee_company_not_last_company_id(): void
    {
        // Panel şirket değişimi simülasyonu — last_company_id = B
        app(CompanyContextService::class)->rememberLastCompany($this->dualUser, (int) $this->companyB->id);
        $this->assertSame($this->companyB->id, (int) $this->dualUser->fresh()->last_company_id);
        $this->assertSame($this->companyA->id, (int) $this->dualUser->fresh()->home_company_id);

        Sanctum::actingAs($this->dualUser->fresh());
        $this->postJson('/api/v1/portal/timesheet/clock-in', [
            'latitude' => 41.0,
            'longitude' => 29.0,
        ])->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->dualUser->id,
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('attendance_records', [
            'user_id' => $this->dualUser->id,
            'company_id' => $this->companyB->id,
        ]);
    }

    public function test_portal_qr_punch_uses_employee_company_not_last_company_id(): void
    {
        app(CompanyContextService::class)->rememberLastCompany($this->dualUser, (int) $this->companyB->id);
        $this->assertSame($this->companyB->id, (int) $this->dualUser->fresh()->last_company_id);

        $token = CompanyContext::run($this->companyA->id, function () {
            return app(AttendanceKioskTokenService::class)->issue(
                (int) $this->companyA->id,
                (int) $this->branchA->id,
                (int) $this->adminA->id
            )['token'];
        });

        Sanctum::actingAs($this->dualUser->fresh());
        $this->postJson('/api/v1/portal/timesheet/qr-scan', ['token' => $token])->assertOk();

        $record = AttendanceRecord::query()->where('user_id', $this->dualUser->id)->first();
        $this->assertNotNull($record);
        $this->assertSame($this->companyA->id, (int) $record->company_id);
        $this->assertNotSame($this->companyB->id, (int) $record->company_id);
    }
}
