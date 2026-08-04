<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\DataSubjectRequestChannel;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectType;
use App\Enums\UserType;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\DataSubjectRequest;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\RequestType;
use App\Models\SalaryReviewPeriod;
use App\Models\User;
use App\Services\Approval\ApprovalEntityRegistry;
use App\Services\CompanyContextService;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur3 B3 — ApprovalEntityRegistry üzerindeki tüm entity'ler için
 * aktif bağlam A iken B kaydına erişim → 403/404 (veya scope null).
 *
 * Faz 6'da yeni registry kaydı otomatik kapsanır.
 */
class ApprovalEntityIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $userA;

    private User $userB;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org AE', 'slug' => 'org-ae']);
        $this->companyA = Company::factory()->create([
            'name' => 'AE Company A',
            'slug' => 'ae-company-a',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'AE Company B',
            'slug' => 'ae-company-b',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);

        $this->userA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assignSpatieAdminRole($this->userA->fresh());
        app(CompanyContextService::class)->ensureMembership($this->userA, (int) $this->companyB->id, false);

        $this->userB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->userB->fresh());

        $this->employeeB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
            'status' => 'active',
        ]);
    }

    /** @return array<string, string> */
    private function headersA(): array
    {
        return ['X-Company-Id' => (string) $this->companyA->id];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function registeredEntityTypes(): array
    {
        // Registry bootstrap için app gerekir — setUp sonrası instance dataProvider kullanılamaz.
        // Sabit liste registry ile test içinde doğrulanır.
        return [
            ApprovalWorkflow::ENTITY_LEAVE_REQUEST => [ApprovalWorkflow::ENTITY_LEAVE_REQUEST],
            ApprovalWorkflow::ENTITY_EXPENSE_REQUEST => [ApprovalWorkflow::ENTITY_EXPENSE_REQUEST],
            ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST => [ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST],
            ApprovalWorkflow::ENTITY_SALARY_REVIEW => [ApprovalWorkflow::ENTITY_SALARY_REVIEW],
            ApprovalWorkflow::ENTITY_DATA_SUBJECT_REQUEST => [ApprovalWorkflow::ENTITY_DATA_SUBJECT_REQUEST],
        ];
    }

    /**
     * @dataProvider registeredEntityTypes
     */
    public function test_active_context_a_cannot_access_company_b_approval_entity(string $entityType): void
    {
        $registry = app(ApprovalEntityRegistry::class);
        $this->assertTrue($registry->has($entityType), "Registry'de eksik: {$entityType}");

        // Registry'deki tüm kayıtlar bu provider'da olmalı
        $allKeys = array_keys($registry->all());
        sort($allKeys);
        $providerKeys = array_keys(self::registeredEntityTypes());
        sort($providerKeys);
        $this->assertSame($allKeys, $providerKeys, 'Yeni registry entity eklendi — bu teste probe ekleyin');

        Sanctum::actingAs($this->userA);
        $status = $this->probeForeignAccess($entityType);
        $this->assertContains(
            $status,
            [403, 404],
            "{$entityType}: aktif A iken B kaydı {$status} döndü (403/404 beklenir)"
        );
    }

    private function probeForeignAccess(string $entityType): int
    {
        return match ($entityType) {
            ApprovalWorkflow::ENTITY_LEAVE_REQUEST => $this->probeLeave(),
            ApprovalWorkflow::ENTITY_EXPENSE_REQUEST => $this->probeExpense(),
            ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST => $this->probeEmployeeRequest(),
            ApprovalWorkflow::ENTITY_SALARY_REVIEW => $this->probeSalaryReview(),
            ApprovalWorkflow::ENTITY_DATA_SUBJECT_REQUEST => $this->probeDataSubjectRequest(),
            default => 500,
        };
    }

    private function probeLeave(): int
    {
        $leaveType = LeaveType::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Yıllık B',
            'code' => 'YL-AE-B',
            'is_active' => true,
            'is_paid' => true,
        ]);
        $leave = LeaveRequest::query()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'status' => 'pending',
        ]);

        return $this->getJson('/api/v1/leaves/requests/'.$leave->id, $this->headersA())->status();
    }

    private function probeExpense(): int
    {
        $expense = ExpenseClaim::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
        ]);

        return $this->getJson('/api/v1/expenses/claims/'.$expense->id, $this->headersA())->status();
    }

    private function probeEmployeeRequest(): int
    {
        // Company panel show yok — BelongsToCompany scope = 404 eşdeğeri
        $type = RequestType::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Avans',
            'slug' => 'avans-ae',
            'is_active' => true,
            'requires_approval' => true,
        ]);
        $row = EmployeeRequest::query()->create([
            'company_id' => $this->companyB->id,
            'employee_id' => $this->employeeB->id,
            'request_type_id' => $type->id,
            'title' => 'Avans B',
            'status' => EmployeeRequest::STATUS_PENDING,
            'priority' => EmployeeRequest::PRIORITY_NORMAL,
            'created_by' => $this->userB->id,
        ]);

        $found = CompanyContext::run($this->companyA->id, fn () => EmployeeRequest::query()->find($row->id));

        return $found === null ? 404 : 200;
    }

    private function probeSalaryReview(): int
    {
        $period = SalaryReviewPeriod::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Zam B',
            'scope_type' => SalaryReviewPeriod::SCOPE_COMPANY,
            'effective_date' => now()->toDateString(),
            'status' => SalaryReviewPeriod::STATUS_DRAFT,
            'created_by' => $this->userB->id,
        ]);

        return $this->getJson('/api/v1/salary-reviews/'.$period->id, $this->headersA())->status();
    }

    private function probeDataSubjectRequest(): int
    {
        $row = DataSubjectRequest::query()->create([
            'company_id' => $this->companyB->id,
            'subject_type' => DataSubjectType::Employee,
            'subject_id' => $this->employeeB->id,
            'applicant_name' => 'B Subject',
            'contact' => 'b-subject@example.com',
            'request_types' => ['erisim'],
            'description' => 'AE izolasyon',
            'channel' => DataSubjectRequestChannel::Written,
            'status' => DataSubjectRequestStatus::New,
            'identity_verified' => false,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        return $this->getJson('/api/v1/kvkk/data-subject-requests/'.$row->id, $this->headersA())->status();
    }
}
