<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\JobApplicationStatus;
use App\Enums\JobPositionStatus;
use App\Enums\UserType;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\ExpenseClaim;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Payslip;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySubmission;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\Reports\DatasetRegistry;
use App\Services\Reports\ReportQueryBuilder;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur5 / G2 — DatasetRegistry her dataset: scope=company B yok; scope=group A+B, C yok.
 */
class DatasetRegistryIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyC;

    private User $userA;

    private User $userB;

    private User $userC;

    private Employee $employeeA;

    private Employee $employeeB;

    private Employee $employeeC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $orgX = Organization::query()->create(['name' => 'Org DS X', 'slug' => 'org-ds-x-g2']);
        $orgY = Organization::query()->create(['name' => 'Org DS Y', 'slug' => 'org-ds-y-g2']);
        $this->companyA = Company::factory()->create([
            'name' => 'DS Company A',
            'slug' => 'ds-a-g2',
            'status' => CompanyStatus::Active,
            'organization_id' => $orgX->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'DS Company B',
            'slug' => 'ds-b-g2',
            'status' => CompanyStatus::Active,
            'organization_id' => $orgX->id,
        ]);
        $this->companyC = Company::factory()->create([
            'name' => 'DS Company C',
            'slug' => 'ds-c-g2',
            'status' => CompanyStatus::Active,
            'organization_id' => $orgY->id,
        ]);

        $this->userA = User::factory()->create([
            'home_company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assignSpatieAdminRole($this->userA->fresh());
        app(CompanyContextService::class)->ensureMembership($this->userA, (int) $this->companyB->id, false);

        $this->userB = User::factory()->create([
            'home_company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->userB->fresh());

        $this->userC = User::factory()->create([
            'home_company_id' => $this->companyC->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->userC->fresh());

        $this->employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'status' => 'active',
        ]);
        $this->employeeB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
            'status' => 'active',
        ]);
        $this->employeeC = Employee::factory()->create([
            'company_id' => $this->companyC->id,
            'user_id' => $this->userC->id,
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function registeredDatasets(): array
    {
        return [
            'employees' => ['employees'],
            'leave_requests' => ['leave_requests'],
            'leave_balances' => ['leave_balances'],
            'expense_claims' => ['expense_claims'],
            'job_applications' => ['job_applications'],
            'survey_responses' => ['survey_responses'],
            'attendance_records' => ['attendance_records'],
            'assets' => ['assets'],
            'training_participants' => ['training_participants'],
            'employee_documents' => ['employee_documents'],
            'payslips' => ['payslips'],
        ];
    }

    /**
     * @dataProvider registeredDatasets
     */
    public function test_active_context_a_excludes_company_b_dataset_row(string $datasetKey): void
    {
        $registry = app(DatasetRegistry::class);
        $this->assertTrue($registry->has($datasetKey), "Registry'de eksik: {$datasetKey}");

        $allKeys = array_keys($registry->all());
        sort($allKeys);
        $providerKeys = array_keys(self::registeredDatasets());
        sort($providerKeys);
        $this->assertSame($allKeys, $providerKeys, 'Yeni dataset eklendi — bu teste probe ekleyin');

        $foreignId = $this->seedForeignRow($datasetKey);

        Sanctum::actingAs($this->userA);
        $ids = CompanyContext::run($this->companyA->id, function () use ($datasetKey) {
            $result = app(ReportQueryBuilder::class)->run($this->userA, $this->companyA->id, [
                'dataset' => $datasetKey,
                'fields' => ['id'],
                'limit' => 200,
            ]);
            $out = [];
            foreach ($result['rows'] as $row) {
                if (isset($row['id'])) {
                    $out[] = (int) $row['id'];
                }
            }

            return $out;
        });

        $this->assertNotContains(
            $foreignId,
            $ids,
            "{$datasetKey}: aktif A iken B satırı #{$foreignId} listede olmamalı"
        );
    }

    /**
     * @dataProvider registeredDatasets
     */
    public function test_scope_group_includes_b_excludes_c(string $datasetKey): void
    {
        $idB = $this->seedRow($datasetKey, $this->companyB, $this->userB, $this->employeeB, 'B');
        $idC = $this->seedRow($datasetKey, $this->companyC, $this->userC, $this->employeeC, 'C');

        Sanctum::actingAs($this->userA);
        $ids = CompanyContext::run($this->companyA->id, function () use ($datasetKey) {
            $result = app(ReportQueryBuilder::class)->run($this->userA, $this->companyA->id, [
                'dataset' => $datasetKey,
                'fields' => ['id'],
                'scope' => 'group',
                'limit' => 200,
            ]);
            $out = [];
            foreach ($result['rows'] as $row) {
                if (isset($row['id'])) {
                    $out[] = (int) $row['id'];
                }
            }

            return $out;
        });

        $this->assertContains($idB, $ids, "{$datasetKey}: group kapsamında B satırı #{$idB} olmalı");
        $this->assertNotContains($idC, $ids, "{$datasetKey}: group kapsamında C satırı #{$idC} olmamalı");

        if ($datasetKey === 'employees') {
            $this->assertContains($this->employeeA->id, $ids);
        }
    }

    public function test_scope_group_without_permission_forbidden(): void
    {
        $role = \Spatie\Permission\Models\Role::findOrCreate('g2_no_group', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->syncPermissions([
            'reports.definitions.view',
            'reports.definitions.run',
            'employees.list.view',
        ]);
        $user = User::factory()->create([
            'home_company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $user->assignRole($role);
        app(CompanyContextService::class)->ensureMembership($user, (int) $this->companyA->id, true);
        app(CompanyContextService::class)->ensureMembership($user, (int) $this->companyB->id, false);

        Sanctum::actingAs($user->fresh()->load('roles'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        CompanyContext::run($this->companyA->id, function () use ($user) {
            app(ReportQueryBuilder::class)->run($user->fresh()->load('roles'), $this->companyA->id, [
                'dataset' => 'employees',
                'fields' => ['id'],
                'scope' => 'group',
            ]);
        });
    }

    private function seedForeignRow(string $datasetKey): int
    {
        return $this->seedRow($datasetKey, $this->companyB, $this->userB, $this->employeeB, 'B');
    }

    private function seedRow(string $datasetKey, Company $company, User $user, Employee $employee, string $tag): int
    {
        return match ($datasetKey) {
            'employees' => (int) $employee->id,
            'leave_requests' => $this->seedLeaveRequest($company, $user, $tag),
            'leave_balances' => $this->seedLeaveBalance($company, $user, $tag),
            'expense_claims' => (int) ExpenseClaim::factory()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
            ])->id,
            'job_applications' => $this->seedJobApplication($company, $tag),
            'survey_responses' => $this->seedSurveyResponse($company, $user, $tag),
            'attendance_records' => (int) AttendanceRecord::factory()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'date' => now()->toDateString(),
            ])->id,
            'assets' => $this->seedAsset($company, $tag),
            'training_participants' => $this->seedTrainingParticipant($company, $user, $tag),
            'employee_documents' => (int) EmployeeDocument::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'title' => 'Doc '.$tag,
                'category' => 'other',
                'file_path' => strtolower($tag).'.pdf',
                'file_name' => strtolower($tag).'.pdf',
                'file_type' => 'application/pdf',
                'file_size' => 10,
                'uploaded_by' => $user->id,
                'created_by' => $user->id,
            ])->id,
            'payslips' => (int) Payslip::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'period' => '2026-01',
                'year' => 2026,
                'month' => 1,
                'gross_salary' => 1000,
                'net_salary' => 800,
                'is_published' => true,
                'created_by' => $user->id,
            ])->id,
            default => 0,
        };
    }

    private function seedLeaveRequest(Company $company, User $user, string $tag): int
    {
        $type = LeaveType::query()->create([
            'company_id' => $company->id,
            'name' => 'Yıllık '.$tag,
            'code' => 'YL-DS-'.$tag,
            'is_active' => true,
            'is_paid' => true,
        ]);

        return (int) LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'status' => 'pending',
        ])->id;
    }

    private function seedLeaveBalance(Company $company, User $user, string $tag): int
    {
        $type = LeaveType::query()->create([
            'company_id' => $company->id,
            'name' => 'Bakiye '.$tag,
            'code' => 'BK-DS-'.$tag,
            'is_active' => true,
            'is_paid' => true,
        ]);

        return (int) LeaveBalance::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'leave_type_id' => $type->id,
            'year' => (int) now()->year,
            'total_days' => 14,
            'used_days' => 0,
            'pending_days' => 0,
            'carried_over' => 0,
        ])->id;
    }

    private function seedJobApplication(Company $company, string $tag): int
    {
        $position = JobPosition::query()->create([
            'company_id' => $company->id,
            'title' => 'Pozisyon '.$tag,
            'slug' => 'pozisyon-'.strtolower($tag).'-ds-g2',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'published_at' => now(),
        ]);

        return (int) JobApplication::query()->create([
            'company_id' => $company->id,
            'job_position_id' => $position->id,
            'first_name' => 'Aday',
            'last_name' => $tag,
            'email' => 'aday-'.strtolower($tag).'-ds@example.com',
            'status' => JobApplicationStatus::New,
            'consent_kvkk' => true,
        ])->id;
    }

    private function seedSurveyResponse(Company $company, User $user, string $tag): int
    {
        $survey = Survey::query()->create([
            'company_id' => $company->id,
            'title' => 'Anket '.$tag,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $question = SurveyQuestion::query()->create([
            'survey_id' => $survey->id,
            'question_text' => 'Q?',
            'question_type' => 'text',
            'order_number' => 1,
        ]);
        $submission = SurveySubmission::query()->create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        return (int) SurveyResponse::query()->create([
            'survey_submission_id' => $submission->id,
            'survey_question_id' => $question->id,
            'answer_text' => 'ans-'.$tag,
        ])->id;
    }

    private function seedAsset(Company $company, string $tag): int
    {
        $category = AssetCategory::query()->create([
            'company_id' => $company->id,
            'name' => 'Kat '.$tag,
            'is_active' => true,
        ]);

        return (int) Asset::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Varlık '.$tag,
            'asset_code' => 'AST-DS-'.$tag,
            'status' => 'available',
            'condition' => 'good',
        ])->id;
    }

    private function seedTrainingParticipant(Company $company, User $user, string $tag): int
    {
        $training = Training::query()->create([
            'company_id' => $company->id,
            'title' => 'Eğitim '.$tag,
            'created_by' => $user->id,
        ]);
        $session = TrainingSession::query()->create([
            'training_id' => $training->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        return (int) TrainingParticipant::query()->create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'status' => 'registered',
            'registered_at' => now(),
        ])->id;
    }
}
