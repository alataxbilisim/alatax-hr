<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AttendanceKioskToken;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Module;
use App\Models\User;
use App\Services\Timesheet\AttendanceKioskTokenService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * PDKS-1: QR kiosk token + portal scan.
 */
class PdksQrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private Branch $branchA;

    private User $adminA;

    private User $employeeA;

    private User $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['admin', 'employee', 'manager'] as $role) {
            Role::findOrCreate($role, 'sanctum');
        }

        $this->companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->companyB = Company::factory()->create(['status' => CompanyStatus::Active]);

        $mod = Module::firstOrCreate(
            ['slug' => 'timesheet'],
            ['name' => 'Puantaj', 'is_core' => false, 'is_active' => true]
        );
        $this->companyA->modules()->syncWithoutDetaching([
            $mod->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'name' => 'Merkez',
            'code' => 'MRK',
            'is_active' => true,
            'is_headquarters' => true,
        ]);

        $this->adminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->adminA->assignRole('admin');
        $this->adminA->givePermissionTo([
            'timesheet.kiosk.view',
            'timesheet.attendance.view',
        ]);

        $this->employeeA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $this->employeeA->assignRole('employee');
        Employee::factory()->forUser($this->employeeA)->create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
        ]);

        $this->employeeB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        Employee::factory()->forUser($this->employeeB)->create([
            'company_id' => $this->companyB->id,
        ]);
    }

    public function test_kiosk_token_requires_permission(): void
    {
        Sanctum::actingAs($this->employeeA);

        $this->postJson('/api/v1/attendance/kiosk/token', [
            'branch_id' => $this->branchA->id,
        ])->assertStatus(403);
    }

    public function test_valid_qr_clock_in_then_out_with_branch_and_source(): void
    {
        Sanctum::actingAs($this->adminA);
        $issue = $this->postJson('/api/v1/attendance/kiosk/token', [
            'branch_id' => $this->branchA->id,
        ])->assertStatus(200);

        $token = $issue->json('data.token');
        $this->assertNotEmpty($token);

        Sanctum::actingAs($this->employeeA);
        $scan1 = $this->postJson('/api/v1/portal/timesheet/qr-scan', [
            'token' => $token,
        ])->assertStatus(200);

        $this->assertSame('clock_in', $scan1->json('data.action'));
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->employeeA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'source' => 'qr',
        ]);

        // Yeni token ile çıkış
        Sanctum::actingAs($this->adminA);
        $token2 = $this->postJson('/api/v1/attendance/kiosk/token', [
            'branch_id' => $this->branchA->id,
        ])->json('data.token');

        Sanctum::actingAs($this->employeeA);
        $scan2 = $this->postJson('/api/v1/portal/timesheet/qr-scan', [
            'token' => $token2,
        ])->assertStatus(200);

        $this->assertSame('clock_out', $scan2->json('data.action'));
        $record = AttendanceRecord::query()
            ->where('user_id', $this->employeeA->id)
            ->whereDate('date', now()->toDateString())
            ->first();
        $this->assertNotNull($record?->clock_out);
    }

    public function test_expired_token_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:00'));

        Sanctum::actingAs($this->adminA);
        $token = $this->postJson('/api/v1/attendance/kiosk/token')->json('data.token');

        Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:45'));

        Sanctum::actingAs($this->employeeA);
        $this->postJson('/api/v1/portal/timesheet/qr-scan', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertSame(0, AttendanceRecord::query()->where('user_id', $this->employeeA->id)->count());

        Carbon::setTestNow();
    }

    public function test_used_token_rejected(): void
    {
        Sanctum::actingAs($this->adminA);
        $token = $this->postJson('/api/v1/attendance/kiosk/token', [
            'branch_id' => $this->branchA->id,
        ])->json('data.token');

        Sanctum::actingAs($this->employeeA);
        $this->postJson('/api/v1/portal/timesheet/qr-scan', ['token' => $token])->assertStatus(200);

        $this->postJson('/api/v1/portal/timesheet/qr-scan', ['token' => $token])
            ->assertStatus(422);

        $this->assertTrue(
            AttendanceKioskToken::query()->whereNotNull('used_at')->exists()
        );
    }

    public function test_cross_tenant_qr_rejected_no_record(): void
    {
        Sanctum::actingAs($this->adminA);
        $token = $this->postJson('/api/v1/attendance/kiosk/token', [
            'branch_id' => $this->branchA->id,
        ])->json('data.token');

        Sanctum::actingAs($this->employeeB);
        $this->postJson('/api/v1/portal/timesheet/qr-scan', ['token' => $token])
            ->assertStatus(422);

        $this->assertSame(0, AttendanceRecord::query()->count());
        $this->assertNull(
            AttendanceKioskToken::query()->whereNotNull('used_at')->first()
        );
    }

    public function test_tampered_signature_rejected(): void
    {
        $issued = app(AttendanceKioskTokenService::class)->issue(
            (int) $this->companyA->id,
            (int) $this->branchA->id,
            (int) $this->adminA->id
        );

        $parts = explode('.', $issued['token']);
        $parts[2] = str_repeat('a', 64);
        $bad = implode('.', $parts);

        Sanctum::actingAs($this->employeeA);
        $this->postJson('/api/v1/portal/timesheet/qr-scan', ['token' => $bad])
            ->assertStatus(422);
    }
}
