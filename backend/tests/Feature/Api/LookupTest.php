<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\User;
use App\Services\LookupService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $plainUser;

    private Company $company;

    private Company $otherCompany;

    private LookupService $lookups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminUser);
        $this->adminUser = $this->adminUser->fresh();

        $this->plainUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);

        $this->lookups = app(LookupService::class);
    }

    /** @test */
    public function unauthenticated_cannot_read_lookups(): void
    {
        $this->getJson('/api/v1/lookups/employee_status')->assertStatus(401);
    }

    /** @test */
    public function system_seed_includes_currency_city_blood(): void
    {
        $this->assertDatabaseHas('lookups', [
            'lookup_type' => 'currency',
            'value' => 'TRY',
            'is_system' => true,
            'company_id' => null,
        ]);
        $this->assertDatabaseHas('lookups', [
            'lookup_type' => 'city_tr',
            'value' => 'İstanbul',
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('lookups', [
            'lookup_type' => 'blood_type',
            'value' => 'A Rh+',
            'is_system' => true,
        ]);
        $this->assertGreaterThanOrEqual(81, Lookup::where('lookup_type', 'city_tr')->count());
    }

    /** @test */
    public function for_type_returns_merged_active_values(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/lookups/employee_status');
        $response->assertOk()->assertJsonPath('success', true);

        $values = collect($response->json('data'))->pluck('value')->all();
        $this->assertContains('active', $values);
        $this->assertContains('on_leave', $values);
    }

    /** @test */
    public function resolve_returns_label_for_value(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/lookups-resolve?lookup_type=employee_status&value=active');
        $response->assertOk()
            ->assertJsonPath('data.value', 'active')
            ->assertJsonPath('data.label', 'Aktif');
    }

    /** @test */
    public function ka_rename_label_reflects_on_existing_employee_without_changing_status_value(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $default = Lookup::whereNull('company_id')
            ->where('lookup_type', 'employee_status')
            ->where('value', 'active')
            ->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$default->id}", [
            'label' => 'Çalışıyor',
        ])->assertOk();

        $this->assertSame('active', $employee->fresh()->status);

        $show = $this->getJson("/api/v1/employees/{$employee->id}");
        $show->assertOk();
        $payload = $show->json('data.employee') ?? $show->json('data');
        $this->assertSame('active', $payload['status']);
        $this->assertSame('Çalışıyor', $payload['status_label']);
    }

    /** @test */
    public function kb_used_value_is_deactivated_not_hard_deleted(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $default = Lookup::whereNull('company_id')
            ->where('lookup_type', 'employee_status')
            ->where('value', 'active')
            ->firstOrFail();

        $response = $this->deleteJson("/api/v1/lookups-manage/{$default->id}");
        $response->assertOk();
        $this->assertStringContainsString('pasif', mb_strtolower($response->json('message')));

        $this->assertDatabaseHas('lookups', [
            'company_id' => $this->company->id,
            'lookup_type' => 'employee_status',
            'value' => 'active',
            'is_active' => false,
        ]);

        // Platform default hala duruyor
        $this->assertDatabaseHas('lookups', [
            'company_id' => null,
            'lookup_type' => 'employee_status',
            'value' => 'active',
            'is_active' => true,
        ]);

        // Yeni form listesinde active yok
        $forType = $this->getJson('/api/v1/lookups/employee_status');
        $values = collect($forType->json('data'))->pluck('value')->all();
        $this->assertNotContains('active', $values);
    }

    /** @test */
    public function kb_unused_company_value_can_be_hard_deleted(): void
    {
        Sanctum::actingAs($this->adminUser);

        $created = $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'employee_status',
            'value' => 'contractor',
            'label' => 'Taşeron',
            'sort_order' => 90,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->deleteJson("/api/v1/lookups-manage/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Lookup değeri silindi');

        $this->assertDatabaseMissing('lookups', [
            'id' => $id,
        ]);
    }

    /** @test */
    public function system_lookup_update_returns_403(): void
    {
        Sanctum::actingAs($this->adminUser);

        $currency = Lookup::where('lookup_type', 'currency')->where('value', 'TRY')->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$currency->id}", [
            'label' => 'Türk Lirası Hack',
        ])->assertStatus(403);

        $this->deleteJson("/api/v1/lookups-manage/{$currency->id}")->assertStatus(403);
    }

    /** @test */
    public function work_type_shared_by_employee_and_job_position(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employeeTypes = collect($this->getJson('/api/v1/lookups/work_type')->json('data'))->pluck('value');
        $this->assertTrue($employeeTypes->contains('full_time'));
        $this->assertTrue($employeeTypes->contains('internship'));
        $this->assertTrue($employeeTypes->contains('hybrid'));

        $this->lookups->assertValid(LookupService::TYPE_WORK_TYPE, 'internship', $this->company->id, 'employment_type');
        $this->lookups->assertValid(LookupService::TYPE_WORK_TYPE, 'hybrid', $this->company->id, 'work_type');
    }

    /** @test */
    public function management_permission_enforced_on_crud(): void
    {
        Sanctum::actingAs($this->plainUser);

        $this->getJson('/api/v1/lookups-manage?lookup_type=employee_status')->assertStatus(403);
        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'employee_status',
            'label' => 'X',
            'value' => 'x_status',
        ])->assertStatus(403);

        // Form okuma (forType) auth ile açık
        $this->getJson('/api/v1/lookups/employee_status')->assertOk();
    }

    /** @test */
    public function tenant_isolation_on_company_override(): void
    {
        Sanctum::actingAs($this->adminUser);

        $default = Lookup::whereNull('company_id')
            ->where('lookup_type', 'employee_status')
            ->where('value', 'active')
            ->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$default->id}", [
            'label' => 'FirmaA Aktif',
        ])->assertOk();

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);

        Sanctum::actingAs($otherAdmin->fresh());
        $label = $this->getJson('/api/v1/lookups-resolve?lookup_type=employee_status&value=active')
            ->json('data.label');

        $this->assertSame('Aktif', $label);
    }

    /** @test */
    public function unauthorized_role_cannot_manage_even_with_company_admin_type_without_permission(): void
    {
        // company_admin type ama lookups permission yok
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        // Rol yok / permission yok
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'employee_status',
            'value' => 'temp_x',
            'label' => 'Temp',
        ]);

        // company_admin middleware geçebilir; permission middleware 403
        $this->assertContains($response->status(), [403, 401]);
    }
}
