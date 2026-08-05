<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/** Tur8 — full_name backfill: users.name → employees.full_name */
class EmployeeFullNameBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_fills_empty_full_name_from_user_and_list_shows_it(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin->fresh());

        $linked = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Backfill Adı',
            'type' => UserType::User,
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'T8-BF-01',
            'full_name' => null,
            'user_id' => $linked->id,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        Artisan::call('employees:backfill-full-name');

        $this->assertSame('Backfill Adı', $employee->fresh()->full_name);

        Sanctum::actingAs($admin->fresh());
        // show: { data: { employee: EmployeeResource, ... } }
        $this->getJson("/api/v1/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.employee.full_name', 'Backfill Adı')
            ->assertJsonPath('data.employee.name', 'Backfill Adı');

        $list = $this->getJson('/api/v1/employees?per_page=50')->assertOk();
        $row = collect($list->json('data.data') ?? $list->json('data'))
            ->firstWhere('id', $employee->id);
        $this->assertNotNull($row);
        $this->assertSame('Backfill Adı', $row['full_name'] ?? $row['name'] ?? null);
    }
}
