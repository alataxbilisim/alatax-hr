<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4A-3 Zincir 2 — request_types.form_fields → form_data validasyon + tenant.
 */
class PortalRequestFormFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LookupSeeder::class);

        $this->companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->companyB = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->userA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($this->userA)->create(['company_id' => $this->companyA->id]);

        $this->userB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($this->userB)->create(['company_id' => $this->companyB->id]);
    }

    public function test_database_is_pgsql_testing(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('alatax_hr_testing', DB::connection()->getDatabaseName());
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/v1/portal/requests', [])->assertStatus(401);
    }

    public function test_empty_form_fields_type_creates_without_form_data(): void
    {
        $type = RequestType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Genel Talep',
            'slug' => 'genel',
            'is_active' => true,
            'requires_attachment' => false,
            'form_fields' => null,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $type->id,
            'title' => 'Boş şema talep',
            'priority' => 'normal',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employee_requests', [
            'title' => 'Boş şema talep',
            'company_id' => $this->companyA->id,
        ]);
    }

    public function test_form_fields_required_violation_returns_422(): void
    {
        $type = RequestType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Avans',
            'slug' => 'avans',
            'is_active' => true,
            'requires_attachment' => false,
            'form_fields' => [
                [
                    'id' => 'amount',
                    'type' => 'number',
                    'label' => 'Tutar',
                    'required' => true,
                ],
            ],
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($this->userA);

        $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $type->id,
            'title' => 'Avans talep',
            'priority' => 'normal',
            'form_data' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_form_fields_submit_writes_form_data(): void
    {
        $type = RequestType::create([
            'company_id' => $this->companyA->id,
            'name' => 'Avans',
            'slug' => 'avans-ok',
            'is_active' => true,
            'requires_attachment' => false,
            'form_fields' => [
                [
                    'name' => 'amount',
                    'type' => 'number',
                    'label' => 'Tutar',
                    'required' => true,
                ],
                [
                    'key' => 'note',
                    'type' => 'textarea',
                    'label' => 'Not',
                    'required' => false,
                ],
            ],
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $type->id,
            'title' => 'Avans 1500',
            'priority' => 'normal',
            'form_data' => [
                'amount' => 1500,
                'note' => 'Acil',
            ],
        ]);

        $response->assertStatus(201);
        $row = EmployeeRequest::where('title', 'Avans 1500')->first();
        $this->assertNotNull($row);
        $this->assertSame(1500, $row->form_data['amount'] ?? null);
        $this->assertSame('Acil', $row->form_data['note'] ?? null);
    }

    public function test_tenant_isolation_cannot_use_other_company_request_type(): void
    {
        $typeB = RequestType::create([
            'company_id' => $this->companyB->id,
            'name' => 'B Tipi',
            'slug' => 'b-tip',
            'is_active' => true,
            'form_fields' => null,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($this->userA);

        $this->postJson('/api/v1/portal/requests', [
            'request_type_id' => $typeB->id,
            'title' => 'Cross tenant',
            'priority' => 'normal',
        ])->assertStatus(422);
    }
}
