<?php

namespace App\Services\Onboarding;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\OnboardingProcess;
use App\Models\OnboardingTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Onboarding süreç başlatma — manuel store + hired→convert tek kod yolu.
 *
 * Şablon seçimi (otomatik):
 *  (a) company is_default + is_active
 *  (b) department/position eşlemesi — şemada YOK → KARAR BEKLENİYOR (uygulanmadı)
 *  (c) yoksa süreç başlatılmaz; uyarı döner
 */
class OnboardingProcessService
{
    /**
     * Manuel / otomatik ortak süreç oluşturma.
     *
     * @param  array{
     *   company_id: int,
     *   user_id: int,
     *   template_id?: int|null,
     *   title: string,
     *   start_date: string|\DateTimeInterface,
     *   target_end_date?: string|\DateTimeInterface|null,
     *   notes?: string|null,
     *   assigned_to?: int|null,
     *   created_by?: int|null,
     *   process_type?: string,
     *   employee_id?: int|null,
     *   termination_reason_code?: string|null,
     *   termination_date?: string|\DateTimeInterface|null,
     *   exit_notes?: string|null,
     *   remaining_leave_days?: float|null
     * }  $data
     */
    public function startProcess(array $data): OnboardingProcess
    {
        $companyId = (int) $data['company_id'];
        $userId = (int) $data['user_id'];
        $processType = $data['process_type'] ?? OnboardingProcess::TYPE_ONBOARDING;

        if ($userId <= 0) {
            throw ValidationException::withMessages([
                'user_id' => ['Süreç için kullanıcı zorunludur.'],
            ]);
        }

        $templateId = isset($data['template_id']) ? (int) $data['template_id'] : null;
        if ($templateId) {
            $template = OnboardingTemplate::query()
                ->where('company_id', $companyId)
                ->where('id', $templateId)
                ->first();
            if ($template === null) {
                throw ValidationException::withMessages([
                    'template_id' => ['Şablon bu firmaya ait değil veya bulunamadı.'],
                ]);
            }
            if ($template->process_type !== $processType) {
                throw ValidationException::withMessages([
                    'template_id' => ['Şablon süreci tipiyle uyuşmuyor.'],
                ]);
            }
        }

        return DB::transaction(function () use ($data, $companyId, $templateId, $processType) {
            $process = OnboardingProcess::create([
                'company_id' => $companyId,
                'process_type' => $processType,
                'user_id' => (int) $data['user_id'],
                'employee_id' => isset($data['employee_id']) ? (int) $data['employee_id'] : null,
                'template_id' => $templateId ?: null,
                'title' => $data['title'],
                'start_date' => $data['start_date'],
                'target_end_date' => $data['target_end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'termination_reason_code' => $data['termination_reason_code'] ?? null,
                'termination_date' => $data['termination_date'] ?? null,
                'exit_notes' => $data['exit_notes'] ?? null,
                'remaining_leave_days' => $data['remaining_leave_days'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'status' => OnboardingProcess::STATUS_PENDING,
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['created_by'] ?? null,
            ]);

            if ($process->template_id) {
                $process->load('template');
                $process->createTasksFromTemplate();
            }

            $label = $processType === OnboardingProcess::TYPE_OFFBOARDING
                ? 'İşten çıkış süreci başlatıldı: '
                : 'Onboarding süreci başlatıldı: ';
            ActivityLog::log('create', $process, $label.$process->title);

            return $process->load(['user', 'template', 'tasks']);
        });
    }

    /**
     * Firma varsayılan aktif şablon (is_default) — tip filtreli.
     */
    public function resolveDefaultTemplate(
        int $companyId,
        string $processType = OnboardingTemplate::TYPE_ONBOARDING
    ): ?OnboardingTemplate {
        return OnboardingTemplate::query()
            ->where('company_id', $companyId)
            ->where('process_type', $processType)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Convert sonrası otomatik tetikleme.
     *
     * @return array{
     *   started: bool,
     *   skipped: bool,
     *   warning: string|null,
     *   process: OnboardingProcess|null
     * }
     */
    public function startForEmployee(Employee $employee, ?int $actorId = null): array
    {
        $userId = $employee->user_id;
        if ($userId === null) {
            return [
                'started' => false,
                'skipped' => true,
                'warning' => 'Onboarding başlatılamadı: personelin kullanıcı kaydı yok.',
                'process' => null,
            ];
        }

        $existing = OnboardingProcess::query()
            ->where('company_id', $employee->company_id)
            ->where('user_id', $userId)
            ->where('process_type', OnboardingProcess::TYPE_ONBOARDING)
            ->active()
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return [
                'started' => false,
                'skipped' => true,
                'warning' => null,
                'process' => $existing->load(['user', 'template', 'tasks']),
            ];
        }

        $template = $this->resolveDefaultTemplate(
            (int) $employee->company_id,
            OnboardingTemplate::TYPE_ONBOARDING
        );
        if ($template === null) {
            return [
                'started' => false,
                'skipped' => true,
                'warning' => 'Onboarding şablonu yok',
                'process' => null,
            ];
        }

        $startDate = $employee->hire_date
            ? $employee->hire_date->toDateString()
            : now()->toDateString();
        $targetEnd = $template->estimated_days
            ? \Illuminate\Support\Carbon::parse($startDate)->addDays((int) $template->estimated_days)->toDateString()
            : null;

        $process = $this->startProcess([
            'company_id' => (int) $employee->company_id,
            'user_id' => (int) $userId,
            'template_id' => $template->id,
            'title' => $template->name,
            'start_date' => $startDate,
            'target_end_date' => $targetEnd,
            'notes' => 'İşe alım dönüşümü ile otomatik başlatıldı',
            'created_by' => $actorId,
            'process_type' => OnboardingProcess::TYPE_ONBOARDING,
        ]);

        return [
            'started' => true,
            'skipped' => false,
            'warning' => null,
            'process' => $process,
        ];
    }
}
