<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingProcess extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    const STATUS_PENDING = 'pending';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    public const TYPE_ONBOARDING = 'onboarding';

    public const TYPE_OFFBOARDING = 'offboarding';

    protected $fillable = [
        'company_id',
        'process_type',
        'user_id',
        'employee_id',
        'template_id',
        'title',
        'start_date',
        'target_end_date',
        'actual_end_date',
        'status',
        'progress',
        'notes',
        'termination_reason_code',
        'termination_date',
        'exit_notes',
        'remaining_leave_days',
        'assigned_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_end_date' => 'date',
        'actual_end_date' => 'date',
        'termination_date' => 'date',
        'remaining_leave_days' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function template()
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function tasks()
    {
        return $this->hasMany(OnboardingTask::class, 'process_id')->orderBy('order');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('process_type', $type);
    }

    public function isOffboarding(): bool
    {
        return $this->process_type === self::TYPE_OFFBOARDING;
    }

    // Methods
    public function updateProgress(): void
    {
        $total = $this->tasks()->count();
        $completed = $this->tasks()->whereIn('status', [
            OnboardingTask::STATUS_COMPLETED,
            OnboardingTask::STATUS_SKIPPED,
        ])->count();

        $this->progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        if ($this->progress === 100) {
            // Offboarding: otomatik kapanmaz — "Çıkışı Tamamla" gerekir
            if ($this->isOffboarding()) {
                $this->status = self::STATUS_IN_PROGRESS;
            } else {
                $this->status = self::STATUS_COMPLETED;
                $this->actual_end_date = now();
            }
        } elseif ($this->progress > 0) {
            $this->status = self::STATUS_IN_PROGRESS;
        }

        $this->save();
    }

    public function createTasksFromTemplate(): void
    {
        if (! $this->template) {
            return;
        }

        foreach ($this->template->tasks as $index => $taskData) {
            $actionKey = $taskData['action_key'] ?? null;
            $task = OnboardingTask::create([
                'company_id' => $this->company_id,
                'process_id' => $this->id,
                'title' => $taskData['title'] ?? 'Görev '.($index + 1),
                'description' => $taskData['description'] ?? null,
                'type' => $taskData['type'] ?? 'custom',
                'order' => $index,
                'is_required' => $taskData['is_required'] ?? true,
                'due_date' => isset($taskData['days_offset'])
                    ? $this->start_date->copy()->addDays($taskData['days_offset'])
                    : null,
                'assigned_to' => $taskData['assigned_to'] ?? $this->assigned_to,
                'data' => $actionKey ? ['action_key' => $actionKey] : null,
            ]);

            if ($task->assigned_to) {
                app(\App\Services\Notification\NotificationService::class)
                    ->notifyOnboardingTaskAssigned($task->load('process'));
            }
        }
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Bekliyor',
            self::STATUS_IN_PROGRESS => 'Devam Ediyor',
            self::STATUS_COMPLETED => 'Tamamlandı',
            self::STATUS_CANCELLED => 'İptal Edildi',
        ];
    }
}
