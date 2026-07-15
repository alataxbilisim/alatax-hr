<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\SalaryBand;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SalaryBandTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $hr;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->hr = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();
        $this->hr->assignRole('admin');
        $this->hr->givePermissionTo(['employees.salary.view', 'employees.salary.edit']);
        $this->hr = $this->hr->fresh();

        $this->position = Position::create([
            'company_id' => $this->company->id,
            'code' => 'DEV',
            'name' => 'Yazılım Geliştirici',
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 10,
        ]);
    }

    public function test_band_crud_and_indicator(): void
    {
        Sanctum::actingAs($this->hr);

        $this->postJson('/api/v1/salary-bands', [
            'position_id' => $this->position->id,
            'min_amount' => 30000,
            'mid_amount' => 45000,
            'max_amount' => 60000,
            'currency' => 'TRY',
        ])->assertStatus(201);

        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'position' => 'Yazılım Geliştirici',
            'gross_salary' => 50000,
            'status' => 'active',
        ]);
        $this->hr->givePermissionTo(['employees.list.view']);

        $res = $this->getJson("/api/v1/employees/{$emp->id}/salary")->assertOk();
        $this->assertSame('within', $res->json('data.band.status'));

        $noPos = Employee::factory()->create([
            'company_id' => $this->company->id,
            'position' => null,
            'gross_salary' => 50000,
            'status' => 'active',
        ]);
        $this->getJson("/api/v1/employees/{$noPos->id}/salary")
            ->assertOk()
            ->assertJsonPath('data.band.band', null);
    }

    public function test_without_salary_view_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/salary-bands')->assertStatus(403);
    }
}
