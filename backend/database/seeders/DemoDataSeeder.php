<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DataSubjectRequestChannel;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectType;
use App\Enums\ExperienceLevel;
use App\Enums\JobPositionStatus;
use App\Enums\KvkkConsentType;
use App\Enums\RetentionStrategy;
use App\Enums\RetentionTriggerEvent;
use App\Enums\UserType;
use App\Models\Announcement;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ConsentRecord;
use App\Models\DataSubjectRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\Holiday;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\PrivacyNotice;
use App\Models\RequestType;
use App\Models\RetentionPolicy;
use App\Models\Role;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySubmission;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\DefaultCompanyHrSeedService;
use App\Services\DefaultLeaveApprovalWorkflowService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * QA-1 demo veri seti (idempotent). Production'da çalışmaz.
 * php artisan demo:seed — şifre: Demo1234!
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo1234!';

    /** @var array<string, User> */
    private array $qaUsers = [];

    /** @var array<string, Employee> */
    private array $employeesByCode = [];

    /** @var array<string, Branch> */
    private array $branches = [];

    /** @var array<string, Department> */
    private array $departments = [];

    /** @var array<string, Position> */
    private array $positions = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DemoDataSeeder production ortamında çalıştırılamaz.');
        }

        $company = $this->seedCompany();
        $this->seedOrgStructure($company);
        $this->seedUsers($company);
        $this->seedEmployees($company);
        $this->seedLeaveData($company);
        $this->seedAttendance($company);
        $this->seedExpenses($company);
        $this->seedAssets($company);
        $this->seedDocuments($company);
        $this->seedTraining($company);
        $this->seedSurvey($company);
        $this->seedPayslips($company);
        $this->seedRecruitment($company);
        $this->seedAnnouncements($company);
        $this->seedWorkflows($company);
        $this->seedRequestTypes($company);
        $this->seedKvkk($company);

        $this->command?->info('DemoDataSeeder tamam (idempotent).');
        $this->command?->table(
            ['E-posta', 'Ad', 'Rol', 'Şifre'],
            [
                ['admin@demo.test', 'Demo Admin', 'admin', self::PASSWORD],
                ['ik@demo.test', 'Demo İK Uzmanı', 'hr_specialist', self::PASSWORD],
                ['mudur@demo.test', 'Demo Departman Müdürü', 'manager', self::PASSWORD],
                ['personel@demo.test', 'Demo Personel', 'employee', self::PASSWORD],
                ['rapor@demo.test', 'Demo Raporcu', 'demo_report_viewer', self::PASSWORD],
                ['hr@demo.test', 'Demo İK (legacy)', 'hr_manager', self::PASSWORD],
                ['sube-ist@demo.test', 'İstanbul Şube Yöneticisi', 'branch_manager', self::PASSWORD],
                ['sube-ank@demo.test', 'Ankara Şube Yöneticisi', 'branch_manager', self::PASSWORD],
            ]
        );
    }

    private function seedCompany(): Company
    {
        // Katalogda eksik satılabilir modüller / izinler olabilir — idempotent seeders
        $this->call(ModuleSeeder::class);
        $this->call(PermissionSeeder::class);
        if (class_exists(SystemReportPackageSeeder::class)) {
            $this->call(SystemReportPackageSeeder::class);
        }

        $company = Company::firstOrCreate(
            ['slug' => 'demo-firma'],
            [
                'name' => 'Demo Firma AŞ',
                'status' => 'active',
                'package_type' => 'professional',
                'user_limit' => 200,
                'trial_ends_at' => now()->addYear(),
            ]
        );
        $company->update(['name' => 'Demo Firma AŞ', 'status' => 'active']);

        $sync = [];
        foreach (Module::query()->where('is_active', true)->get() as $module) {
            $sync[$module->id] = ['is_active' => true, 'activated_at' => now()];
        }
        $company->modules()->syncWithoutDetaching($sync);

        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($company);
        app(DefaultCompanyHrSeedService::class)->ensureForCompany($company);
        if (method_exists(Holiday::class, 'seedTurkishHolidaysForYears')) {
            Holiday::seedTurkishHolidaysForYears([2026, 2027, 2028]);
        }

        return $company->fresh();
    }

    private function seedOrgStructure(Company $company): void
    {
        foreach (['HQ' => ['Merkez', 'İstanbul', true], 'IST' => ['İstanbul', 'İstanbul', false], 'ANK' => ['Ankara', 'Ankara', false]] as $code => [$name, $city, $hq]) {
            $this->branches[$code] = Branch::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'city' => $city, 'is_active' => true, 'is_headquarters' => $hq]
            );
        }

        foreach (['IK' => 'İnsan Kaynakları', 'IT' => 'Bilgi Teknolojileri', 'SAT' => 'Satış', 'OPR' => 'Operasyon', 'FIN' => 'Finans', 'PAZ' => 'Pazarlama'] as $code => $name) {
            $this->departments[$code] = Department::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }

        $posNames = [
            'İK Uzmanı', 'Yazılım Geliştirici', 'Satış Temsilcisi', 'Operasyon Uzmanı',
            'Mali İşler Uzmanı', 'Pazarlama Uzmanı', 'Kıdemli Yazılım Geliştirici',
            'Departman Müdürü', 'Genel Müdür', 'Sistem Yöneticisi', 'Müşteri Hizmetleri', 'Lojistik Uzmanı',
        ];
        foreach ($posNames as $i => $name) {
            $code = sprintf('DEMO_POS_%02d', $i + 1);
            $this->positions[$code] = Position::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'is_active' => true, 'sort_order' => $i + 1]
            );
        }
    }

    private function seedUsers(Company $company): void
    {
        $role = Role::firstOrCreate(['name' => 'demo_report_viewer', 'guard_name' => 'sanctum']);
        if (Schema::hasColumn('roles', 'data_scope')) {
            $role->forceFill(['data_scope' => 'company'])->save();
        }
        $perms = [];
        foreach ([
            'reports.definitions.view', 'reports.definitions.create', 'reports.definitions.edit',
            'reports.definitions.run', 'reports.measures.view', 'reports.schedules.view',
            'reports.dashboards.view', 'reports.view', 'reports.export',
        ] as $name) {
            $perms[] = Permission::findOrCreate($name, 'sanctum');
        }
        $role->syncPermissions($perms);

        $defs = [
            ['admin@demo.test', 'Demo Admin', 'admin', UserType::CompanyAdmin],
            ['ik@demo.test', 'Demo İK Uzmanı', 'hr_specialist', UserType::User],
            ['mudur@demo.test', 'Demo Departman Müdürü', 'manager', UserType::User],
            ['personel@demo.test', 'Demo Personel', 'employee', UserType::User],
            ['rapor@demo.test', 'Demo Raporcu', 'demo_report_viewer', UserType::User],
            ['hr@demo.test', 'Demo İK', 'hr_manager', UserType::User],
            ['sube-ist@demo.test', 'İstanbul Şube Yöneticisi', 'branch_manager', UserType::User],
            ['sube-ank@demo.test', 'Ankara Şube Yöneticisi', 'branch_manager', UserType::User],
        ];
        foreach ($defs as [$email, $name, $roleName, $type]) {
            $this->qaUsers[$email] = $this->upsertUser($company->id, $email, $name, $roleName, $type);
        }
    }

    private function seedEmployees(Company $company): void
    {
        $admin = $this->qaUsers['admin@demo.test'];
        $ik = $this->qaUsers['ik@demo.test'];
        $mudur = $this->qaUsers['mudur@demo.test'];
        $personel = $this->qaUsers['personel@demo.test'];

        $adminEmp = $this->upsertEmployee($company, 'DEM-001', $admin, 'IK', 'HQ', 'DEMO_POS_09', '2018-03-01', 'active', 'male');
        $ikEmp = $this->upsertEmployee($company, 'DEM-002', $ik, 'IK', 'HQ', 'DEMO_POS_01', '2019-06-15', 'active', 'female', $adminEmp->id);
        $mudurEmp = $this->upsertEmployee($company, 'DEM-010', $mudur, 'IT', 'HQ', 'DEMO_POS_08', '2018-09-01', 'active', 'male', $adminEmp->id);
        $this->departments['IT']->update(['manager_id' => $mudur->id]);
        $this->departments['SAT']->update(['manager_id' => $mudur->id]);
        $this->upsertEmployee($company, 'DEM-020', $personel, 'SAT', 'IST', 'DEMO_POS_03', '2022-04-10', 'active', 'female', $mudurEmp->id);

        $names = [
            'Ayşe Demir', 'Can Yıldız', 'Elif Kara', 'Burak Şahin', 'Zeynep Ak', 'Mert Çelik', 'Selin Öztürk',
            'Emre Aydın', 'Deniz Kılıç', 'Gökhan Arslan', 'İrem Koç', 'Onur Yılmaz', 'Melis Acar', 'Hakan Polat',
            'Ceren Taş', 'Barış Erdoğan', 'Nazlı Kurt', 'Tolga Şimşek', 'Pınar Avcı', 'Serkan Doğan', 'Ece Yalçın',
            'Murat Keskin', 'Seda Güneş', 'Volkan Aslan', 'Hande Bozkurt', 'Kaan Tekin', 'Derya Çakır', 'Oğuz Demirtaş',
            'Büşra İlhan', 'Alper Soylu', 'Gizem Karaca', 'Yiğit Özkan', 'Tuğçe Bulut', 'Furkan Aksoy', 'Nilüfer Ergin',
            'Cem Uçar', 'Şeyma Korkmaz', 'Arda Sezer', 'Esra Bilgin', 'Berkay Tunç',
        ];
        $deptCodes = ['IK', 'IT', 'SAT', 'OPR', 'FIN', 'PAZ'];
        $branchCodes = ['HQ', 'IST', 'ANK'];
        $posCodes = array_keys($this->positions);
        $terminated = [35, 36, 37, 38, 39, 40];

        for ($i = 1; $i <= 40; $i++) {
            $code = sprintf('DEM-%03d', $i);
            if (isset($this->employeesByCode[$code])) {
                continue;
            }
            $dept = $deptCodes[($i - 1) % 6];
            $isTerm = in_array($i, $terminated, true);
            $status = $isTerm ? 'terminated' : ($i === 33 ? 'on_leave' : 'active');
            $hireYear = ($i % 5 === 0) ? 2018 : (($i % 4 === 0) ? 2025 : 2019 + ($i % 6));
            $managerId = $isTerm ? null : (in_array($dept, ['IT', 'SAT'], true) ? $mudurEmp->id : ($dept === 'IK' ? $ikEmp->id : $adminEmp->id));
            $user = $isTerm ? null : $this->upsertUser($company->id, sprintf('personel%03d@demo.test', $i), $names[($i - 1) % 40], 'employee');
            $this->upsertEmployee(
                $company,
                $code,
                $user,
                $dept,
                $branchCodes[($i - 1) % 3],
                $posCodes[($i - 1) % 12],
                sprintf('%d-%02d-%02d', $hireYear, ($i % 12) + 1, min(28, 1 + ($i % 27))),
                $status,
                $i % 2 === 0 ? 'female' : 'male',
                $managerId,
                $isTerm ? now()->subMonths(($i % 8) + 1)->toDateString() : null
            );
        }
    }

    private function seedLeaveData(Company $company): void
    {
        $annual = LeaveType::query()->where('company_id', $company->id)->where('system_code', 'annual')->first();
        $sick = LeaveType::query()->where('company_id', $company->id)->where('system_code', 'sick')->first();
        if (! $annual) {
            return;
        }

        $year = (int) now()->year;
        $active = collect($this->employeesByCode)->filter(fn (Employee $e) => $e->status === 'active' && $e->user_id)->values();
        foreach ($active as $emp) {
            LeaveBalance::updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $emp->user_id, 'leave_type_id' => $annual->id, 'year' => $year],
                ['total_days' => 14, 'used_days' => 2, 'pending_days' => 0, 'carried_over' => 0]
            );
        }
        if ($active->isEmpty()) {
            return;
        }

        $adminId = $this->qaUsers['admin@demo.test']->id;
        $statuses = [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED];
        for ($i = 1; $i <= 32; $i++) {
            $emp = $active[($i - 1) % $active->count()];
            $status = $statuses[($i - 1) % 4];
            $start = Carbon::parse('2026-03-01')->addDays($i * 3);
            $days = ($i % 4) + 1;
            $reason = sprintf('[DEMO-LR-%03d] Demo izin talebi #%d', $i, $i);
            $payload = [
                'user_id' => $emp->user_id,
                'leave_type_id' => ($i % 5 === 0 && $sick) ? $sick->id : $annual->id,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
                'total_days' => $days,
                'reason' => $reason,
                'status' => $status,
                'workflow_status' => $status === LeaveRequest::STATUS_PENDING ? LeaveRequest::WORKFLOW_PENDING : LeaveRequest::WORKFLOW_COMPLETED,
            ];
            if ($status === LeaveRequest::STATUS_APPROVED) {
                $payload['approved_by'] = $adminId;
                $payload['approved_at'] = now()->subDays(10);
            }
            if ($status === LeaveRequest::STATUS_REJECTED) {
                $payload['rejected_by'] = $adminId;
                $payload['rejected_at'] = now()->subDays(8);
                $payload['rejection_reason'] = 'Demo red gerekçesi';
            }
            LeaveRequest::updateOrCreate(['company_id' => $company->id, 'reason' => $reason], $payload);
        }
    }

    private function seedAttendance(Company $company): void
    {
        $targets = collect([
            $this->qaUsers['personel@demo.test'] ?? null,
            $this->qaUsers['mudur@demo.test'] ?? null,
            $this->qaUsers['ik@demo.test'] ?? null,
            User::where('email', 'personel005@demo.test')->first(),
            User::where('email', 'personel012@demo.test')->first(),
        ])->filter()->unique('id')->take(5)->values();

        $weekdays = [];
        $cursor = now()->subDays(90)->startOfDay();
        while (count($weekdays) < 60) {
            if ($cursor->isWeekday()) {
                $weekdays[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        foreach ($targets as $idx => $user) {
            foreach ($weekdays as $d => $date) {
                $late = ($d + $idx) % 7 === 0;
                $early = ($d + $idx) % 11 === 0;
                $ot = ($d + $idx) % 9 === 0;
                $payload = [
                    'company_id' => $company->id,
                    'clock_in' => $late ? '09:25' : '08:55',
                    'clock_out' => $early ? '16:30' : ($ot ? '19:15' : '18:00'),
                    'total_hours' => $ot ? 9.5 : ($early ? 7.0 : 8.5),
                    'status' => $late ? AttendanceRecord::STATUS_LATE : ($early ? AttendanceRecord::STATUS_EARLY_LEAVE : AttendanceRecord::STATUS_PRESENT),
                    'source' => AttendanceRecord::SOURCE_MANUAL,
                    'is_approved' => true,
                ];
                if (Schema::hasColumn('attendance_records', 'late_minutes')) {
                    $payload['late_minutes'] = $late ? 25 : 0;
                }
                if (Schema::hasColumn('attendance_records', 'early_leave_minutes')) {
                    $payload['early_leave_minutes'] = $early ? 90 : 0;
                }
                if (Schema::hasColumn('attendance_records', 'overtime_hours')) {
                    $payload['overtime_hours'] = $ot ? 1.25 : 0;
                }
                AttendanceRecord::updateOrCreate(['user_id' => $user->id, 'date' => $date], $payload);
            }
        }
    }

    private function seedExpenses(Company $company): void
    {
        $cat = ExpenseCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO_TRAVEL'],
            ['name' => 'Demo Seyahat', 'description' => 'QA demo', 'requires_receipt' => true, 'is_active' => true, 'max_amount' => 5000]
        );
        $user = $this->qaUsers['personel@demo.test'];
        $statuses = [ExpenseClaim::STATUS_DRAFT, ExpenseClaim::STATUS_SUBMITTED, ExpenseClaim::STATUS_APPROVED, ExpenseClaim::STATUS_REJECTED, ExpenseClaim::STATUS_PAID];
        for ($i = 1; $i <= 5; $i++) {
            ExpenseClaim::updateOrCreate(
                ['company_id' => $company->id, 'claim_number' => sprintf('DEMO-EXP-%03d', $i)],
                [
                    'user_id' => $user->id,
                    'title' => 'Demo masraf '.$i,
                    'description' => 'QA harcama ('.$cat->name.')',
                    'expense_date' => now()->subDays($i * 5)->toDateString(),
                    'total_amount' => 250 * $i,
                    'currency' => 'TRY',
                    'status' => $statuses[$i - 1],
                    'submitted_by' => $user->id,
                    'submitted_at' => now()->subDays($i * 4),
                ]
            );
        }
    }

    private function seedAssets(Company $company): void
    {
        $cat = AssetCategory::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo Bilgisayar'],
            ['description' => 'QA demo', 'icon' => 'laptop', 'is_active' => true, 'sort_order' => 1]
        );
        $assignee = $this->qaUsers['personel@demo.test'];
        $admin = $this->qaUsers['admin@demo.test'];
        for ($i = 1; $i <= 5; $i++) {
            $assigned = $i <= 3;
            $asset = Asset::updateOrCreate(
                ['company_id' => $company->id, 'asset_code' => sprintf('DEMO-AST-%03d', $i)],
                [
                    'category_id' => $cat->id,
                    'name' => 'Demo Laptop '.$i,
                    'serial_number' => 'SN-DEMO-'.$i,
                    'brand' => 'DemoMarka',
                    'model' => 'X'.$i,
                    'purchase_date' => '2024-01-15',
                    'purchase_price' => 15000 + ($i * 500),
                    'condition' => AssetCondition::Good,
                    'status' => $assigned ? AssetStatus::Assigned : AssetStatus::Available,
                    'location' => 'Merkez',
                    'created_by' => $admin->id,
                ]
            );
            if ($assigned && class_exists(AssetAssignment::class)) {
                AssetAssignment::updateOrCreate(
                    ['asset_id' => $asset->id, 'user_id' => $assignee->id, 'return_date' => null],
                    [
                        'assigned_date' => now()->subMonths($i)->toDateString(),
                        'notes' => 'Demo zimmet',
                        'condition_at_assignment' => AssetCondition::Good->value,
                        'assigned_by' => $admin->id,
                    ]
                );
            }
        }
    }

    private function seedDocuments(Company $company): void
    {
        $uploader = $this->qaUsers['ik@demo.test'];
        foreach ([
            ['Demo Personel El Kitabı', 'handbook.pdf', 'demo/docs/handbook.pdf'],
            ['Demo İzin Politikası', 'leave-policy.pdf', 'demo/docs/leave-policy.pdf'],
            ['Demo KVKK Formu', 'kvkk-form.pdf', 'demo/docs/kvkk-form.pdf'],
        ] as [$name, $fileName, $path]) {
            Document::updateOrCreate(
                ['company_id' => $company->id, 'file_path' => $path],
                ['name' => $name, 'file_name' => $fileName, 'file_size' => 1024, 'file_type' => 'application/pdf', 'uploaded_by' => $uploader->id, 'version' => 1]
            );
        }
    }

    private function seedTraining(Company $company): void
    {
        if (! class_exists(Training::class) || ! class_exists(TrainingSession::class)) {
            return;
        }
        $creator = $this->qaUsers['ik@demo.test'];
        $training = Training::updateOrCreate(
            ['company_id' => $company->id, 'title' => 'Demo İş Güvenliği Eğitimi'],
            [
                'description' => 'QA demo', 'category' => 'safety', 'type' => 'classroom', 'instructor' => 'Demo Eğitmen',
                'location' => 'Merkez', 'duration_hours' => 4, 'max_participants' => 30, 'is_mandatory' => true,
                'is_active' => true, 'created_by' => $creator->id,
            ]
        );
        $session = TrainingSession::updateOrCreate(
            ['training_id' => $training->id, 'start_date' => '2026-05-10 10:00:00'],
            [
                'end_date' => '2026-05-10 14:00:00', 'location' => 'Merkez', 'instructor' => 'Demo Eğitmen',
                'status' => 'completed', 'notes' => 'Demo oturum', 'created_by' => $creator->id,
            ]
        );
        if (! class_exists(TrainingParticipant::class)) {
            return;
        }
        foreach (['personel@demo.test', 'mudur@demo.test', 'ik@demo.test'] as $email) {
            TrainingParticipant::updateOrCreate(
                ['session_id' => $session->id, 'user_id' => $this->qaUsers[$email]->id],
                ['status' => 'attended', 'score' => 90, 'passed' => true, 'registered_at' => now()->subMonths(2), 'completed_at' => now()->subMonths(2)->addHours(4)]
            );
        }
    }

    private function seedSurvey(Company $company): void
    {
        if (! class_exists(Survey::class)) {
            return;
        }
        $survey = Survey::updateOrCreate(
            ['company_id' => $company->id, 'title' => 'Demo Memnuniyet Anketi'],
            [
                'description' => 'QA demo', 'type' => Survey::TYPE_SATISFACTION, 'is_anonymous' => true, 'is_active' => true,
                'start_date' => now()->subMonth(), 'end_date' => now()->addMonths(2), 'audience' => 'all',
                'created_by' => $this->qaUsers['ik@demo.test']->id,
            ]
        );
        $q1 = SurveyQuestion::updateOrCreate(
            ['survey_id' => $survey->id, 'order_number' => 1],
            ['question_text' => 'Genel memnuniyetinizi puanlayın', 'question_type' => SurveyQuestion::TYPE_RATING, 'min_value' => 1, 'max_value' => 5, 'is_required' => true]
        );
        SurveyQuestion::updateOrCreate(
            ['survey_id' => $survey->id, 'order_number' => 2],
            ['question_text' => 'Önerileriniz nelerdir?', 'question_type' => SurveyQuestion::TYPE_TEXT, 'is_required' => false]
        );
        if (! class_exists(SurveySubmission::class) || ! class_exists(SurveyResponse::class)) {
            return;
        }
        for ($i = 1; $i <= 3; $i++) {
            $sub = SurveySubmission::updateOrCreate(
                ['survey_id' => $survey->id, 'anonymous_id' => 'demo-anon-'.$i],
                ['user_id' => null, 'status' => SurveySubmission::STATUS_COMPLETED, 'started_at' => now()->subDays($i), 'completed_at' => now()->subDays($i)->addMinutes(5)]
            );
            SurveyResponse::updateOrCreate(
                ['survey_submission_id' => $sub->id, 'survey_question_id' => $q1->id],
                ['answer_numeric' => 3 + ($i % 3)]
            );
        }
    }

    private function seedPayslips(Company $company): void
    {
        $publisher = $this->qaUsers['admin@demo.test'];
        foreach (['DEM-001', 'DEM-002', 'DEM-020'] as $code) {
            $emp = $this->employeesByCode[$code] ?? null;
            if (! $emp) {
                continue;
            }
            foreach ([['2026-05', 2026, 5], ['2026-06', 2026, 6]] as [$period, $year, $month]) {
                Payslip::updateOrCreate(
                    ['company_id' => $company->id, 'employee_id' => $emp->id, 'period' => $period],
                    [
                        'year' => $year, 'month' => $month, 'gross_salary' => 45000, 'net_salary' => 32000,
                        'deductions' => [['name' => 'SGK', 'amount' => 5000]], 'bonuses' => [],
                        'total_deductions' => 13000, 'total_bonuses' => 0, 'worked_days' => 22, 'overtime_hours' => 0,
                        'is_published' => true, 'published_at' => now()->subDays(10), 'published_by' => $publisher->id,
                        'notes' => 'Demo bordro '.$period,
                    ]
                );
            }
        }
    }

    private function seedRecruitment(Company $company): void
    {
        $creator = $this->qaUsers['ik@demo.test'];
        $jobs = [];
        foreach ([['yazilim-gelistirici-demo', 'Yazılım Geliştirici', 'IT'], ['ik-uzmani-demo', 'İK Uzmanı', 'İK'], ['satis-temsilcisi-demo', 'Satış Temsilcisi', 'Satış']] as [$slug, $title, $dept]) {
            $jobs[] = JobPosition::updateOrCreate(
                ['company_id' => $company->id, 'slug' => $slug],
                [
                    'title' => $title, 'description' => 'Demo: '.$title, 'requirements' => 'Demo', 'responsibilities' => 'Demo',
                    'department' => $dept, 'location' => 'İstanbul', 'employment_type' => 'full_time',
                    'experience_level' => ExperienceLevel::Mid, 'status' => JobPositionStatus::Active,
                    'positions_count' => 2, 'published_at' => now()->subDays(20), 'created_by' => $creator->id,
                ]
            );
        }
        $stages = [
            JobApplication::STATUS_NEW, JobApplication::STATUS_REVIEWING, JobApplication::STATUS_SHORTLISTED,
            JobApplication::STATUS_INTERVIEW_SCHEDULED, JobApplication::STATUS_INTERVIEWED, JobApplication::STATUS_OFFER_SENT,
            JobApplication::STATUS_HIRED, JobApplication::STATUS_REJECTED, JobApplication::STATUS_WITHDRAWN,
        ];
        $fn = ['Ali', 'Ayşe', 'Mehmet', 'Fatma', 'Ahmet', 'Zeynep', 'Mustafa', 'Elif', 'Hüseyin', 'Merve'];
        $ln = ['Yılmaz', 'Kaya', 'Demir', 'Çelik', 'Şahin', 'Yıldız', 'Öztürk', 'Aydın', 'Arslan', 'Doğan'];
        for ($i = 1; $i <= 20; $i++) {
            JobApplication::updateOrCreate(
                ['company_id' => $company->id, 'job_position_id' => $jobs[($i - 1) % 3]->id, 'email' => sprintf('aday%03d@demo.test', $i)],
                [
                    'first_name' => $fn[($i - 1) % 10], 'last_name' => $ln[($i - 1) % 10],
                    'phone' => sprintf('0532%07d', 1000000 + $i), 'status' => $stages[($i - 1) % 9],
                    'source' => 'demo', 'consent_kvkk' => true, 'consent_at' => now()->subDays($i),
                    'rating' => ($i % 5) + 1, 'notes' => 'Demo başvuru #'.$i,
                    'assigned_to' => $creator->id, 'updated_by' => $creator->id,
                ]
            );
        }
    }

    private function seedAnnouncements(Company $company): void
    {
        $author = $this->qaUsers['admin@demo.test'];
        Announcement::updateOrCreate(
            ['company_id' => $company->id, 'title' => 'Demo Yayınlanmış Duyuru'],
            [
                'content' => 'QA demo duyurusu (yayında).', 'summary' => 'Demo özet', 'type' => 'general',
                'is_for_all' => true, 'is_published' => true, 'published_at' => now()->subDays(3), 'created_by' => $author->id,
            ]
        );
        Announcement::updateOrCreate(
            ['company_id' => $company->id, 'title' => 'Demo Taslak Duyuru'],
            [
                'content' => 'QA demo duyurusu (taslak).', 'summary' => 'Taslak', 'type' => 'general',
                'is_for_all' => true, 'is_published' => false, 'published_at' => null, 'created_by' => $author->id,
            ]
        );
    }

    private function seedWorkflows(Company $company): void
    {
        $mudur = $this->qaUsers['mudur@demo.test'];
        $ik = $this->qaUsers['ik@demo.test'];
        $admin = $this->qaUsers['admin@demo.test'];

        ApprovalWorkflow::query()
            ->where('company_id', $company->id)
            ->where('entity_type', ApprovalWorkflow::ENTITY_LEAVE_REQUEST)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $conditional = ApprovalWorkflow::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo QA Koşullu İzin', 'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST],
            ['description' => 'QA koşullu izin', 'is_active' => true, 'is_default' => true, 'created_by' => $admin->id]
        );
        ApprovalStep::query()->where('approval_workflow_id', $conditional->id)->delete();
        ApprovalStep::create([
            'approval_workflow_id' => $conditional->id, 'step_order' => 1, 'name' => 'Direkt Yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER, 'is_required' => true,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $conditional->id, 'step_order' => 2, 'name' => 'Departman Müdürü (>10 gün)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $mudur->id, 'is_required' => true,
            'condition' => ['field' => 'total_days', 'op' => '>', 'value' => 10],
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $conditional->id, 'step_order' => 3, 'name' => 'İK (opsiyonel)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $ik->id,
            'is_required' => false, 'can_skip' => true,
        ]);

        $parallel = ApprovalWorkflow::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo Paralel İzin', 'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST],
            ['description' => 'QA paralel onay', 'is_active' => true, 'is_default' => false, 'created_by' => $admin->id]
        );
        ApprovalStep::query()->where('approval_workflow_id', $parallel->id)->delete();
        ApprovalStep::create([
            'approval_workflow_id' => $parallel->id, 'step_order' => 1, 'name' => 'Paralel A (Müdür)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $mudur->id, 'is_required' => true,
            'parallel_group' => 1, 'completion_policy' => ApprovalStep::COMPLETION_ALL,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $parallel->id, 'step_order' => 2, 'name' => 'Paralel B (İK)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $ik->id, 'is_required' => true,
            'parallel_group' => 1, 'completion_policy' => ApprovalStep::COMPLETION_ALL,
        ]);

        // W1 — personel talebi (koşullu + paralel)
        ApprovalWorkflow::query()
            ->where('company_id', $company->id)
            ->where('entity_type', ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $reqConditional = ApprovalWorkflow::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo QA Koşullu Talep', 'entity_type' => ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST],
            ['description' => 'W1 koşullu personel talebi', 'is_active' => true, 'is_default' => true, 'created_by' => $admin->id]
        );
        ApprovalStep::query()->where('approval_workflow_id', $reqConditional->id)->delete();
        ApprovalStep::create([
            'approval_workflow_id' => $reqConditional->id, 'step_order' => 1, 'name' => 'Direkt Yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER, 'is_required' => true,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $reqConditional->id, 'step_order' => 2, 'name' => 'İK (yüksek öncelik)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $ik->id, 'is_required' => true,
            'condition' => ['field' => 'priority', 'op' => 'in', 'value' => ['high', 'urgent']],
        ]);

        $reqParallel = ApprovalWorkflow::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo Paralel Talep', 'entity_type' => ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST],
            ['description' => 'W1 paralel personel talebi', 'is_active' => true, 'is_default' => false, 'created_by' => $admin->id]
        );
        ApprovalStep::query()->where('approval_workflow_id', $reqParallel->id)->delete();
        ApprovalStep::create([
            'approval_workflow_id' => $reqParallel->id, 'step_order' => 1, 'name' => 'Paralel A (Müdür)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $mudur->id, 'is_required' => true,
            'parallel_group' => 1, 'completion_policy' => ApprovalStep::COMPLETION_ALL,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $reqParallel->id, 'step_order' => 2, 'name' => 'Paralel B (İK)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $ik->id, 'is_required' => true,
            'parallel_group' => 1, 'completion_policy' => ApprovalStep::COMPLETION_ALL,
        ]);

        // W1 — masraf (koşullu varsayılan)
        ApprovalWorkflow::query()
            ->where('company_id', $company->id)
            ->where('entity_type', ApprovalWorkflow::ENTITY_EXPENSE_REQUEST)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $expConditional = ApprovalWorkflow::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo QA Koşullu Masraf', 'entity_type' => ApprovalWorkflow::ENTITY_EXPENSE_REQUEST],
            ['description' => 'W1 koşullu masraf', 'is_active' => true, 'is_default' => true, 'created_by' => $admin->id]
        );
        ApprovalStep::query()->where('approval_workflow_id', $expConditional->id)->delete();
        ApprovalStep::create([
            'approval_workflow_id' => $expConditional->id, 'step_order' => 1, 'name' => 'Yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER, 'is_required' => true,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $expConditional->id, 'step_order' => 2, 'name' => 'İK (>1000 TL)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $ik->id, 'is_required' => true,
            'condition' => ['field' => 'amount', 'op' => '>', 'value' => 1000],
        ]);

        $expParallel = ApprovalWorkflow::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo Paralel Masraf', 'entity_type' => ApprovalWorkflow::ENTITY_EXPENSE_REQUEST],
            ['description' => 'W1 paralel masraf', 'is_active' => true, 'is_default' => false, 'created_by' => $admin->id]
        );
        ApprovalStep::query()->where('approval_workflow_id', $expParallel->id)->delete();
        ApprovalStep::create([
            'approval_workflow_id' => $expParallel->id, 'step_order' => 1, 'name' => 'Paralel A (Müdür)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $mudur->id, 'is_required' => true,
            'parallel_group' => 1, 'completion_policy' => ApprovalStep::COMPLETION_ALL,
        ]);
        ApprovalStep::create([
            'approval_workflow_id' => $expParallel->id, 'step_order' => 2, 'name' => 'Paralel B (İK)',
            'approver_type' => ApprovalStep::APPROVER_USER, 'specific_user_id' => $ik->id, 'is_required' => true,
            'parallel_group' => 1, 'completion_policy' => ApprovalStep::COMPLETION_ALL,
        ]);
    }

    /**
     * Portal talep tipleri + örnek talepler (W1 employee_request akışı için demo içerik).
     */
    private function seedRequestTypes(Company $company): void
    {
        $admin = $this->qaUsers['admin@demo.test'];
        $personel = $this->employeesByCode['DEM-020'] ?? null;
        if (! $personel) {
            return;
        }

        $types = [
            [
                'slug' => 'belge-talebi',
                'name' => 'Belge Talebi',
                'description' => 'İşe giriş / bordro belgesi talebi',
                'requires_approval' => true,
                'requires_attachment' => false,
                'sort_order' => 1,
                'form_fields' => [
                    ['key' => 'document_kind', 'type' => 'text', 'label' => 'Belge türü', 'required' => true],
                ],
            ],
            [
                'slug' => 'avans-talebi',
                'name' => 'Avans Talebi',
                'description' => 'Maaş avansı',
                'requires_approval' => true,
                'requires_attachment' => false,
                'sort_order' => 2,
                'form_fields' => [
                    ['key' => 'amount', 'type' => 'number', 'label' => 'Tutar', 'required' => true],
                ],
            ],
            [
                'slug' => 'donanim-talebi',
                'name' => 'Donanım Talebi',
                'description' => 'Laptop / monitör / çevre birimi',
                'requires_approval' => true,
                'requires_attachment' => false,
                'sort_order' => 3,
                'form_fields' => [
                    ['key' => 'asset_kind', 'type' => 'text', 'label' => 'Donanım', 'required' => true],
                ],
            ],
        ];

        $typeModels = [];
        foreach ($types as $def) {
            $typeModels[$def['slug']] = RequestType::updateOrCreate(
                ['company_id' => $company->id, 'slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'requires_approval' => $def['requires_approval'],
                    'requires_attachment' => $def['requires_attachment'],
                    'form_fields' => $def['form_fields'],
                    'is_active' => true,
                    'sort_order' => $def['sort_order'],
                    'created_by' => $admin->id,
                ]
            );
        }

        // Örnek talepler — idempotent: title + employee
        $samples = [
            [
                'slug' => 'belge-talebi',
                'title' => 'Demo Belge Talebi',
                'description' => 'QA demo — çalışma belgesi',
                'priority' => 'normal',
                'form_data' => ['document_kind' => 'Çalışma belgesi'],
            ],
            [
                'slug' => 'avans-talebi',
                'title' => 'Demo Avans Talebi',
                'description' => 'QA demo — yüksek öncelik (koşullu adım)',
                'priority' => 'high',
                'form_data' => ['amount' => 5000],
            ],
            [
                'slug' => 'donanim-talebi',
                'title' => 'Demo Donanım Talebi',
                'description' => 'QA demo — monitör',
                'priority' => 'normal',
                'form_data' => ['asset_kind' => 'Monitör'],
            ],
        ];

        $workflow = app(\App\Services\WorkflowService::class);
        foreach ($samples as $sample) {
            $type = $typeModels[$sample['slug']] ?? null;
            if (! $type) {
                continue;
            }
            $existing = EmployeeRequest::query()
                ->where('company_id', $company->id)
                ->where('employee_id', $personel->id)
                ->where('title', $sample['title'])
                ->first();
            if ($existing) {
                continue;
            }

            $req = EmployeeRequest::create([
                'company_id' => $company->id,
                'employee_id' => $personel->id,
                'request_type_id' => $type->id,
                'title' => $sample['title'],
                'description' => $sample['description'],
                'form_data' => $sample['form_data'],
                'priority' => $sample['priority'],
                'status' => EmployeeRequest::STATUS_PENDING,
                'created_by' => $this->qaUsers['personel@demo.test']->id,
            ]);

            $workflow->startWorkflow($req, [
                'requester_id' => $this->qaUsers['personel@demo.test']->id,
                'priority' => $req->priority,
                'request_type_id' => (int) $req->request_type_id,
                'department_id' => $personel->department_id,
            ]);
        }
    }

    private function seedKvkk(Company $company): void
    {
        $publisher = $this->qaUsers['admin@demo.test'];
        PrivacyNotice::updateOrCreate(
            ['company_id' => $company->id, 'audience' => 'employee', 'version' => 1],
            [
                'title' => 'Demo Aydınlatma Metni (Taslak)', 'body' => 'QA taslak aydınlatma.',
                'is_active' => false, 'published_at' => null, 'published_by' => null,
            ]
        );
        $published = PrivacyNotice::updateOrCreate(
            ['company_id' => $company->id, 'audience' => 'employee', 'version' => 2],
            [
                'title' => 'Demo Aydınlatma Metni', 'body' => 'QA yayımlanmış aydınlatma.',
                'effective_from' => now()->subMonth(), 'is_active' => true,
                'published_at' => now()->subMonth(), 'published_by' => $publisher->id,
            ]
        );
        foreach (['DEM-001', 'DEM-002'] as $code) {
            $emp = $this->employeesByCode[$code] ?? null;
            if (! $emp) {
                continue;
            }
            ConsentRecord::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'subject_type' => DataSubjectType::Employee->value,
                    'subject_id' => $emp->id,
                    'consent_type' => KvkkConsentType::NoticeRead->value,
                    'notice_id' => $published->id,
                ],
                [
                    'granted' => true, 'granted_at' => now()->subWeeks(2), 'source' => 'admin',
                    'ip' => '127.0.0.1', 'user_agent' => 'DemoDataSeeder', 'evidence' => ['demo' => true],
                ]
            );
        }
        DataSubjectRequest::updateOrCreate(
            ['company_id' => $company->id, 'contact' => 'kvkk-talep@demo.test', 'description' => '[DEMO-DSR-001] Demo veri sahibi talebi'],
            [
                'subject_type' => DataSubjectType::Employee,
                'subject_id' => $this->employeesByCode['DEM-020']->id ?? null,
                'applicant_name' => 'Demo Personel', 'request_types' => ['bilgi_talebi'],
                'channel' => DataSubjectRequestChannel::Portal, 'status' => DataSubjectRequestStatus::New,
                'due_date' => now()->addDays(30)->toDateString(), 'identity_verified' => false,
                'assigned_to' => $this->qaUsers['ik@demo.test']->id, 'created_by' => $publisher->id,
            ]
        );
        RetentionPolicy::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo Saklama Politikası'],
            [
                'data_category' => 'identity', 'subject_type' => DataSubjectType::FormerEmployee->value,
                'trigger_event' => RetentionTriggerEvent::IstenAyrilma, 'retention_months' => 120,
                'strategy' => RetentionStrategy::Anonymize, 'legal_basis_note' => 'QA demo — dry-run için aktif',
                // QA-2: aday listesi + dry-run görülebilsin; gerçek imha onaylanmaz
                'active' => true, 'requires_approval' => true, 'is_system_draft' => false, 'created_by' => $publisher->id,
            ]
        );
    }

    private function upsertUser(int $companyId, string $email, string $name, string $roleName, UserType $type = UserType::User): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'company_id' => $companyId, 'name' => $name, 'password' => Hash::make(self::PASSWORD),
                'type' => $type, 'is_active' => true, 'must_change_password' => false,
                'preferences' => ['theme' => 'dark', 'locale' => 'tr'],
            ]
        );
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
        if (Schema::hasColumn('roles', 'data_scope') && $role->data_scope === null
            && in_array($roleName, ['admin', 'branch_manager', 'demo_report_viewer', 'hr_specialist', 'manager'], true)) {
            $role->forceFill(['data_scope' => $roleName === 'branch_manager' ? 'branch' : 'company'])->save();
        }
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    private function upsertEmployee(
        Company $company,
        string $code,
        ?User $user,
        string $deptCode,
        string $branchCode,
        string $posCode,
        string $hireDate,
        string $status = 'active',
        ?string $gender = null,
        ?int $managerId = null,
        ?string $terminationDate = null,
    ): Employee {
        $position = $this->positions[$posCode] ?? null;
        $payload = [
            'user_id' => $user?->id,
            'department_id' => $this->departments[$deptCode]->id,
            'branch_id' => $this->branches[$branchCode]->id,
            'title' => $position?->name ?? $posCode,
            'position' => $position?->name ?? $posCode,
            'manager_id' => $managerId,
            'hire_date' => $hireDate,
            'contract_type' => 'permanent',
            'work_type' => 'full_time',
            'status' => $status,
            'personal_email' => $user?->email,
            'currency' => 'TRY',
            'termination_date' => $terminationDate,
            'termination_reason' => $status === 'terminated' ? 'Demo ayrılış' : null,
        ];
        if ($gender !== null && Schema::hasColumn('employees', 'gender')) {
            $payload['gender'] = $gender;
        }
        $emp = Employee::updateOrCreate(['company_id' => $company->id, 'employee_code' => $code], $payload);
        $this->employeesByCode[$code] = $emp;

        return $emp;
    }
}
