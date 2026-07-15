<?php

namespace App\Services\Recruitment;

use App\Enums\JobApplicationStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\OnboardingProcess;
use App\Models\User;
use App\Services\Onboarding\OnboardingProcessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * hired → personel ön-doldurma + varsayılan şablonla onboarding tetikleme (B-5).
 */
class ConvertApplicationToEmployeeService
{
    public function __construct(
        protected OnboardingProcessService $onboardingProcessService,
    ) {}

    /**
     * @param  array{employee_code?: string|null, branch_id?: int|null, hire_date?: string|null, department_id?: int|null}  $options
     * @return array{
     *   employee: Employee,
     *   created: bool,
     *   prefill: array<string, mixed>,
     *   onboarding: array{
     *     started: bool,
     *     skipped: bool,
     *     warning: string|null,
     *     process: OnboardingProcess|null
     *   }
     * }
     */
    public function convert(JobApplication $application, User $actor, array $options = []): array
    {
        $status = $application->status instanceof JobApplicationStatus
            ? $application->status
            : JobApplicationStatus::tryFrom((string) $application->status);

        if ($status !== JobApplicationStatus::Hired) {
            throw new InvalidArgumentException('Yalnızca hired aşamasındaki adaylar personele dönüştürülebilir.');
        }

        if ($application->converted_employee_id) {
            $existing = Employee::query()
                ->withoutGlobalScopes()
                ->where('company_id', $application->company_id)
                ->find($application->converted_employee_id);

            if ($existing) {
                $onboarding = $this->existingOnboardingForEmployee($existing);

                return [
                    'employee' => $existing->load(['user', 'branch', 'department']),
                    'created' => false,
                    'prefill' => $this->prefillFromApplication($application),
                    'onboarding' => $onboarding,
                ];
            }
        }

        return DB::transaction(function () use ($application, $actor, $options) {
            $fullName = trim($application->first_name.' '.$application->last_name);
            $code = $options['employee_code'] ?? $this->generateEmployeeCode((int) $application->company_id);

            $user = User::query()
                ->where('company_id', $application->company_id)
                ->where('email', $application->email)
                ->first();

            if ($user === null) {
                $user = User::create([
                    'company_id' => $application->company_id,
                    'name' => $fullName !== '' ? $fullName : $application->email,
                    'email' => $application->email,
                    'password' => Hash::make(Str::random(40)),
                    'type' => UserType::User,
                    'is_active' => false,
                    'created_by' => $actor->id,
                ]);
            } else {
                if ($user->name === null || trim((string) $user->name) === '') {
                    $user->update(['name' => $fullName]);
                }
            }

            $employee = Employee::create([
                'company_id' => $application->company_id,
                'user_id' => $user->id,
                'employee_code' => $code,
                'branch_id' => $options['branch_id'] ?? null,
                'department_id' => $options['department_id'] ?? null,
                'position' => $application->jobPosition?->title,
                'personal_email' => $application->email,
                'personal_phone' => $application->phone,
                'hire_date' => $options['hire_date'] ?? now()->toDateString(),
                'status' => 'active',
                'notes' => 'İşe alım dönüşümü (başvuru #'.$application->id.')',
                'created_by' => $actor->id,
            ]);

            $application->update(['converted_employee_id' => $employee->id]);

            ActivityLog::log(
                'application_converted_to_employee',
                $application,
                "Aday personele dönüştürüldü: {$fullName} → employee #{$employee->id}",
                ['status' => JobApplicationStatus::Hired->value],
                [
                    'employee_id' => $employee->id,
                    'user_id' => $user->id,
                    'branch_id' => $employee->branch_id,
                ]
            );

            $onboarding = $this->onboardingProcessService->startForEmployee(
                $employee->fresh(),
                $actor->id
            );

            return [
                'employee' => $employee->load(['user', 'branch', 'department']),
                'created' => true,
                'prefill' => $this->prefillFromApplication($application),
                'onboarding' => $onboarding,
            ];
        });
    }

    /**
     * İkinci convert: yeni süreç açılmaz; varsa mevcut aktif süreç döner.
     *
     * @return array{started: bool, skipped: bool, warning: string|null, process: OnboardingProcess|null}
     */
    private function existingOnboardingForEmployee(Employee $employee): array
    {
        if ($employee->user_id === null) {
            return [
                'started' => false,
                'skipped' => true,
                'warning' => null,
                'process' => null,
            ];
        }

        $process = OnboardingProcess::query()
            ->where('company_id', $employee->company_id)
            ->where('user_id', $employee->user_id)
            ->active()
            ->latest('id')
            ->first();

        return [
            'started' => false,
            'skipped' => true,
            'warning' => null,
            'process' => $process?->load(['user', 'template', 'tasks']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prefillFromApplication(JobApplication $application): array
    {
        return [
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'name' => trim($application->first_name.' '.$application->last_name),
            'email' => $application->email,
            'phone' => $application->phone,
            'personal_email' => $application->email,
            'personal_phone' => $application->phone,
            'position' => $application->jobPosition?->title,
        ];
    }

    private function generateEmployeeCode(int $companyId): string
    {
        do {
            $code = 'HIRE-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (
            Employee::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('employee_code', $code)
                ->exists()
        );

        return $code;
    }
}
