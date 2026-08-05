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
 * Tur5 — DatasetRegistry'deki her dataset için aktif bağlam A iken B satırı gelmez.
 * Provider registry ile senkron; yeni dataset eklenince fail.
 */
class DatasetRegistryIsolationTest extends TestCase
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

        $org = Organization::query()->create(['name' => 'Org DS', 'slug' => 'org-ds-t5']);
        $this->companyA = Company::factory()->create([
            'name' => 'DS Company A',
            'slug' => 'ds-a-t5',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'DS Company B',
            'slug' => 'ds-b-t5',
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

    private function seedForeignRow(string $datasetKey): int
    {
        return match ($datasetKey) {
            'employees' => (int) $this->employeeB->id,
            'leave_requests' => $this->seedLeaveRequestB(),
            'leave_balances' => $this->seedLeaveBalanceB(),
            'expense_claims' => (int) ExpenseClaim::factory()->create([
                'company_id' => $this->companyB->id,
                'user_id' => $this->userB->id,
            ])->id,
            'job_applications' => $this->seedJobApplicationB(),
            'survey_responses' => $this->seedSurveyResponseB(),
            'attendance_records' => (int) AttendanceRecord::factory()->create([
                'company_id' => $this->companyB->id,
                'user_id' => $this->userB->id,
                'date' => now()->toDateString(),
            ])->id,
            'assets' => $this->seedAssetB(),
            'training_participants' => $this->seedTrainingParticipantB(),
            'employee_documents' => (int) EmployeeDocument::query()->create([
                'company_id' => $this->companyB->id,
                'employee_id' => $this->employeeB->id,
                'title' => 'Doc B',
                'category' => 'other',
                'file_path' => 'b.pdf',
                'file_name' => 'b.pdf',
                'file_type' => 'application/pdf',
                'file_size' => 10,
                'uploaded_by' => $this->userB->id,
                'created_by' => $this->userB->id,
            ])->id,
            'payslips' => (int) Payslip::query()->create([
                'company_id' => $this->companyB->id,
                'employee_id' => $this->employeeB->id,
                'period' => '2026-01',
                'year' => 2026,
                'month' => 1,
                'gross_salary' => 1000,
                'net_salary' => 800,
                'is_published' => true,
                'created_by' => $this->userB->id,
            ])->id,
            default => 0,
        };
    }

    private function seedLeaveRequestB(): int
    {
        $type = LeaveType::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Yıllık B',
            'code' => 'YL-DS-B',
            'is_active' => true,
            'is_paid' => true,
        ]);

        return (int) LeaveRequest::query()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'status' => 'pending',
        ])->id;
    }

    private function seedLeaveBalanceB(): int
    {
        $type = LeaveType::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Bakiye B',
            'code' => 'BK-DS-B',
            'is_active' => true,
            'is_paid' => true,
        ]);

        return (int) LeaveBalance::query()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
            'leave_type_id' => $type->id,
            'year' => (int) now()->year,
            'total_days' => 14,
            'used_days' => 0,
            'pending_days' => 0,
            'carried_over' => 0,
        ])->id;
    }

    private function seedJobApplicationB(): int
    {
        $position = JobPosition::query()->create([
            'company_id' => $this->companyB->id,
            'title' => 'Pozisyon B',
            'slug' => 'pozisyon-b-ds-t5',
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
            'published_at' => now(),
        ]);

        return (int) JobApplication::query()->create([
            'company_id' => $this->companyB->id,
            'job_position_id' => $position->id,
            'first_name' => 'Aday',
            'last_name' => 'B',
            'email' => 'aday-b-ds@example.com',
            'status' => JobApplicationStatus::New,
            'consent_kvkk' => true,
        ])->id;
    }

    private function seedSurveyResponseB(): int
    {
        $survey = Survey::query()->create([
            'company_id' => $this->companyB->id,
            'title' => 'Anket B',
            'is_active' => true,
            'created_by' => $this->userB->id,
        ]);
        $question = SurveyQuestion::query()->create([
            'survey_id' => $survey->id,
            'question_text' => 'Q?',
            'question_type' => 'text',
            'order_number' => 1,
        ]);
        $submission = SurveySubmission::query()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->userB->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        return (int) SurveyResponse::query()->create([
            'survey_submission_id' => $submission->id,
            'survey_question_id' => $question->id,
            'answer_text' => 'ans-b',
        ])->id;
    }

    private function seedAssetB(): int
    {
        $category = AssetCategory::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Kat B',
            'is_active' => true,
        ]);

        return (int) Asset::query()->create([
            'company_id' => $this->companyB->id,
            'category_id' => $category->id,
            'name' => 'Varlık B',
            'asset_code' => 'AST-DS-B',
            'status' => 'available',
            'condition' => 'good',
        ])->id;
    }

    private function seedTrainingParticipantB(): int
    {
        $training = Training::query()->create([
            'company_id' => $this->companyB->id,
            'title' => 'Eğitim B',
            'created_by' => $this->userB->id,
        ]);
        $session = TrainingSession::query()->create([
            'training_id' => $training->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'status' => 'scheduled',
            'created_by' => $this->userB->id,
        ]);

        return (int) TrainingParticipant::query()->create([
            'session_id' => $session->id,
            'user_id' => $this->userB->id,
            'status' => 'registered',
            'registered_at' => now(),
        ])->id;
    }
}
