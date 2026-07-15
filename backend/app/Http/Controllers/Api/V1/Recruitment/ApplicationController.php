<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Recruitment\ConvertApplicationToEmployeeRequest;
use App\Http\Requests\Recruitment\StoreJobApplicationRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\ActivityLog;
use App\Models\ApplicationStatusLog;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\OnboardingProcess;
use App\Services\LookupService;
use App\Services\Recruitment\ConvertApplicationToEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ApplicationController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
        protected ConvertApplicationToEmployeeService $convertService,
    ) {}

    /**
     * Başvuru listesi (kanban kaynağı) — şema alanları + FE alias.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobApplication::with(['jobPosition']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('position_id') || $request->filled('job_position_id')) {
            $query->where('job_position_id', $request->input('job_position_id', $request->input('position_id')));
        }

        $applications = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        $data = $applications->through(fn (JobApplication $app) => $this->serializeApplication($app));

        return $this->success($data);
    }

    /**
     * Başvuru detayı
     */
    public function show(int $id): JsonResponse
    {
        $application = JobApplication::with(['jobPosition', 'statusLogs.changedBy', 'convertedEmployee.user'])
            ->find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        $payload = $this->serializeApplication($application);
        $payload['form_data'] = $application->form_data ?? [];
        $payload['notes'] = $application->notes;
        $payload['consent_kvkk'] = (bool) $application->consent_kvkk;
        $payload['consent_at'] = $application->consent_at?->toDateTimeString();
        $payload['converted_employee_id'] = $application->converted_employee_id;
        $payload['status_logs'] = $application->statusLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'status' => $log->to_status,
                'notes' => $log->note,
                'user' => $log->changedBy?->name,
                'created_at' => $log->created_at->toDateTimeString(),
            ];
        });

        return $this->success($payload);
    }

    /**
     * HR manuel aday ekleme.
     */
    public function store(StoreJobApplicationRequest $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $validated = $request->validated();

        $position = JobPosition::query()
            ->where('id', $validated['job_position_id'])
            ->where('company_id', $companyId)
            ->first();

        if ($position === null) {
            return $this->forbidden('Pozisyon bu firmaya ait değil.');
        }

        $cvPath = null;
        $cvOriginal = null;
        if ($request->hasFile('cv')) {
            $file = $request->file('cv');
            $cvPath = $file->store('applications/'.$companyId, 'public');
            $cvOriginal = $file->getClientOriginalName();
        }

        $formData = null;
        if ($position->form_id) {
            $position->loadMissing('form');
            $formData = app(\App\Services\PublicApplicationFormService::class)
                ->validateCustomFormData($position, $validated['form_data'] ?? null);
        }

        $application = JobApplication::create([
            'company_id' => $companyId,
            'job_position_id' => $position->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'cv_path' => $cvPath,
            'cv_original_name' => $cvOriginal,
            'form_data' => $formData,
            'status' => JobApplicationStatus::New,
            'source' => $validated['source'] ?? 'manual',
            'notes' => $validated['notes'] ?? null,
            'consent_kvkk' => true,
            'consent_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        ActivityLog::log(
            'create',
            $application,
            'Manuel aday eklendi: '.$application->first_name.' '.$application->last_name
        );

        return $this->created(
            $this->serializeApplication($application->load('jobPosition')),
            'Aday eklendi'
        );
    }

    /**
     * Başvuru durumu güncelle
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        $validated = $request->validate([
            'status' => 'required|string|max:100',
            'notes' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_APPLICATION_STAGE,
            $validated['status'],
            $companyId,
            'status'
        );

        $oldStatus = $application->status instanceof \BackedEnum
            ? $application->status->value
            : (string) $application->status;

        $note = $validated['notes'] ?? $validated['note'] ?? null;

        $application->update([
            'status' => $validated['status'],
            'updated_by' => auth()->id(),
        ]);

        ApplicationStatusLog::create([
            'job_application_id' => $application->id,
            'from_status' => $oldStatus,
            'to_status' => $validated['status'],
            'note' => $note,
            'changed_by' => auth()->id(),
        ]);

        ActivityLog::log('update', $application, "Başvuru durumu güncellendi: {$oldStatus} -> {$validated['status']}");

        return $this->success(
            $this->serializeApplication($application->fresh()->load('jobPosition')),
            'Durum güncellendi'
        );
    }

    /**
     * Başvuru notlarını güncelle
     */
    public function updateNotes(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $oldNotes = $application->notes;
        $application->update(['notes' => $validated['notes']]);

        ActivityLog::log('update', $application, 'Başvuru notları güncellendi', ['notes' => $oldNotes], ['notes' => $validated['notes']]);

        return $this->success($this->serializeApplication($application->fresh()->load('jobPosition')), 'Notlar güncellendi');
    }

    /**
     * Başvuru puanla
     */
    public function rate(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $oldRating = $application->rating;
        $application->update(['rating' => $validated['rating']]);

        ActivityLog::log('update', $application, "Başvuru puanlandı: {$oldRating} -> {$validated['rating']}", ['rating' => $oldRating], ['rating' => $validated['rating']]);

        return $this->success($this->serializeApplication($application->fresh()->load('jobPosition')), 'Puan güncellendi');
    }

    /**
     * hired → personel ön-doldurma + varsayılan onboarding tetikleme (B-5).
     */
    public function convertToEmployee(ConvertApplicationToEmployeeRequest $request, int $id): JsonResponse
    {
        $application = JobApplication::with('jobPosition')->find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        try {
            $result = $this->convertService->convert(
                $application,
                $request->user(),
                $request->validated()
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $onboarding = $result['onboarding'];
        $process = $onboarding['process'] ?? null;

        $message = $result['created']
            ? 'Personel kaydı ön-dolduruldu'
            : 'Personel kaydı zaten mevcut (idempotent)';
        if (! empty($onboarding['warning'])) {
            $message .= ' — '.$onboarding['warning'];
        } elseif (! empty($onboarding['started'])) {
            $message .= ' — Onboarding süreci başlatıldı';
        }

        return $this->success([
            'employee' => new EmployeeResource($result['employee']),
            'created' => $result['created'],
            'prefill' => $result['prefill'],
            'application_id' => $application->id,
            'converted_employee_id' => $result['employee']->id,
            'onboarding' => [
                'started' => (bool) ($onboarding['started'] ?? false),
                'skipped' => (bool) ($onboarding['skipped'] ?? true),
                'warning' => $onboarding['warning'] ?? null,
                'process_id' => $process?->id,
                'process_title' => $process?->title,
                'process_status' => $process?->status,
            ],
        ], $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApplication(JobApplication $app): array
    {
        $status = $app->status instanceof \BackedEnum
            ? $app->status->value
            : (string) $app->status;

        $fullName = trim(($app->first_name ?? '').' '.($app->last_name ?? ''));
        $position = $app->jobPosition;

        return [
            'id' => $app->id,
            'first_name' => $app->first_name,
            'last_name' => $app->last_name,
            'full_name' => $fullName,
            'applicant_name' => $fullName, // FE alias
            'email' => $app->email,
            'applicant_email' => $app->email,
            'phone' => $app->phone,
            'applicant_phone' => $app->phone,
            'job_position_id' => $app->job_position_id,
            'position' => $position ? [
                'id' => $position->id,
                'title' => $position->title,
            ] : null,
            'job_position' => $position ? [
                'id' => $position->id,
                'title' => $position->title,
            ] : null,
            'status' => $status,
            'form_data' => $app->form_data ?? [],
            'cv_path' => $app->cv_path ? asset('storage/'.$app->cv_path) : null,
            'notes' => $app->notes,
            'rating' => $app->rating,
            'source' => $app->source,
            'consent_kvkk' => (bool) $app->consent_kvkk,
            'converted_employee_id' => $app->converted_employee_id,
            'onboarding_process' => $this->resolveOnboardingSummary($app),
            'created_at' => $app->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array{id: int, title: string, status: string}|null
     */
    private function resolveOnboardingSummary(JobApplication $app): ?array
    {
        if (! $app->converted_employee_id) {
            return null;
        }

        $employee = Employee::query()
            ->withoutGlobalScopes()
            ->where('company_id', $app->company_id)
            ->find($app->converted_employee_id);

        if ($employee === null || $employee->user_id === null) {
            return null;
        }

        $process = OnboardingProcess::query()
            ->where('company_id', $app->company_id)
            ->where('user_id', $employee->user_id)
            ->latest('id')
            ->first();

        if ($process === null) {
            return null;
        }

        return [
            'id' => $process->id,
            'title' => $process->title,
            'status' => $process->status,
        ];
    }
}
