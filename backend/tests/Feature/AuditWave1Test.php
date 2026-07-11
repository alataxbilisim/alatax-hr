<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 2 Audit v2 Dalga 1 — Auditable + Employee pilot + Geçmiş + hassas okuma.
 */
class AuditWave1Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'employees.list.view',
            'employees.list.edit',
            'employees.list.create',
            'employees.list.delete',
            'employees.*',
            'employees.salary.view',
            'employees.salary.edit',
            'employees.tckn.view',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);
    }

    private function hrManager(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $user->assignRole('hr_manager');
        $user->givePermissionTo(['employees.*']);

        return $user;
    }

    public function test_employee_update_creates_diff_only_changed_fields(): void
    {
        $hr = $this->hrManager();
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Eski',
            'position' => 'Dev',
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);
        ActivityLog::query()->delete();

        $this->putJson("/api/v1/employees/{$emp->id}", [
            'title' => 'Yeni',
        ])->assertStatus(200);

        $logs = ActivityLog::where('model_type', Employee::class)
            ->where('model_id', $emp->id)
            ->where('action', 'update')
            ->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertArrayHasKey('title', $log->new_values);
        $this->assertEquals('Yeni', $log->new_values['title']);
        $this->assertEquals('Eski', $log->old_values['title']);
        $this->assertArrayNotHasKey('position', $log->new_values ?? []);
        $this->assertArrayNotHasKey('updated_at', $log->new_values ?? []);
        $this->assertArrayNotHasKey('updated_by', $log->new_values ?? []);
    }

    public function test_salary_change_is_masked_in_audit(): void
    {
        $hr = $this->hrManager();
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'gross_salary' => 50000,
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);
        ActivityLog::query()->delete();

        $this->putJson("/api/v1/employees/{$emp->id}", [
            'gross_salary' => 77777,
        ])->assertStatus(200);

        $log = ActivityLog::where('model_type', Employee::class)
            ->where('model_id', $emp->id)
            ->where('action', 'update')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('*** güncellendi', $log->old_values['gross_salary'] ?? null);
        $this->assertEquals('*** güncellendi', $log->new_values['gross_salary'] ?? null);
        $this->assertStringNotContainsString('77777', json_encode($log->old_values));
        $this->assertStringNotContainsString('77777', json_encode($log->new_values));
        $this->assertStringNotContainsString('50000', json_encode($log->new_values));
        $this->assertStringContainsString('maaş güncellendi', $log->description);
    }

    public function test_employee_history_endpoint_returns_logs_chronological(): void
    {
        $hr = $this->hrManager();
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'A',
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);
        ActivityLog::query()->delete();

        $this->putJson("/api/v1/employees/{$emp->id}", ['title' => 'B'])->assertStatus(200);
        $this->putJson("/api/v1/employees/{$emp->id}", ['title' => 'C'])->assertStatus(200);

        $response = $this->getJson("/api/v1/employees/{$emp->id}/activity")->assertStatus(200);
        $items = $response->json('data');
        $this->assertCount(2, $items);
        $this->assertEquals(Employee::class, $items[0]['model_type']);
        // kronolojik desc: son güncelleme önce
        $this->assertEquals('C', $items[0]['new_values']['title'] ?? null);
        $this->assertEquals('B', $items[1]['new_values']['title'] ?? null);
    }

    public function test_history_is_tenant_isolated(): void
    {
        $hr = $this->hrManager();
        $own = Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $other = Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);
        ActivityLog::withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'action' => 'update',
            'model_type' => Employee::class,
            'model_id' => $other->id,
            'description' => 'other company log',
            'is_successful' => true,
        ]);

        $own->update(['title' => 'Own Title']);

        $response = $this->getJson("/api/v1/employees/{$own->id}/activity")->assertStatus(200);
        $descriptions = collect($response->json('data'))->pluck('description');
        $this->assertFalse($descriptions->contains('other company log'));

        $this->getJson("/api/v1/employees/{$other->id}/activity")->assertStatus(404);
    }

    public function test_no_duplicate_manual_and_observer_logs_on_update(): void
    {
        $hr = $this->hrManager();
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'X',
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);
        ActivityLog::query()->delete();

        $this->putJson("/api/v1/employees/{$emp->id}", ['title' => 'Y'])->assertStatus(200);

        $count = ActivityLog::where('model_type', Employee::class)
            ->where('model_id', $emp->id)
            ->where('action', 'update')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_payslip_show_creates_sensitive_view_log(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $user->assignRole('employee');
        $emp = Employee::factory()->forUser($user)->create();

        $payslip = Payslip::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'period' => now()->format('Y-m'),
            'year' => (int) now()->format('Y'),
            'month' => (int) now()->format('m'),
            'gross_salary' => 10000,
            'net_salary' => 8000,
            'is_published' => true,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($user);
        ActivityLog::query()->delete();

        $this->getJson("/api/v1/portal/payslips/{$payslip->id}")->assertStatus(200);

        $this->assertTrue(
            ActivityLog::where('action', 'view_sensitive')
                ->where('model_type', Payslip::class)
                ->where('model_id', $payslip->id)
                ->where('description', 'bordro görüntülendi')
                ->exists()
        );
    }

    public function test_salary_view_on_show_logs_sensitive_read_not_normal_view(): void
    {
        $hr = $this->hrManager();
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'gross_salary' => 50000,
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);
        ActivityLog::query()->delete();

        $this->getJson("/api/v1/employees/{$emp->id}")->assertStatus(200);

        $this->assertTrue(
            ActivityLog::where('action', 'view_sensitive')
                ->where('model_type', Employee::class)
                ->where('model_id', $emp->id)
                ->where('description', 'maaş bilgisi görüntülendi')
                ->exists()
        );

        // Normal "view" action üretilmez
        $this->assertFalse(
            ActivityLog::where('action', 'view')
                ->where('model_id', $emp->id)
                ->exists()
        );
    }

    public function test_show_without_salary_permission_does_not_log_salary_view(): void
    {
        $specialist = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $specialist->assignRole('hr_specialist');
        $specialist->givePermissionTo(['employees.list.view', 'employees.list.edit']);
        Role::findByName('hr_specialist', 'sanctum')->update(['data_scope' => 'company']);

        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'gross_salary' => 50000,
            'status' => 'active',
        ]);

        Sanctum::actingAs($specialist);
        ActivityLog::query()->delete();

        $this->getJson("/api/v1/employees/{$emp->id}")->assertStatus(200);

        $this->assertFalse(
            ActivityLog::where('action', 'view_sensitive')
                ->where('model_id', $emp->id)
                ->exists()
        );
    }
}
