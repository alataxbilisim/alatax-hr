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

    protected $fillable = [
        'company_id',
        'user_id',
        'template_id',
        'title',
        'start_date',
        'target_end_date',
        'actual_end_date',
        'status',
        'progress',
        'notes',
        'assigned_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_end_date' => 'date',
        'actual_end_date' => 'date',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
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

    // Methods
    public function updateProgress(): void
    {
        $total = $this->tasks()->count();
        $completed = $this->tasks()->where('status', 'completed')->count();

        $this->progress = $total > 0 ? round(($completed / $total) * 100) : 0;

        if ($this->progress === 100) {
            $this->status = self::STATUS_COMPLETED;
            $this->actual_end_date = now();
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
            OnboardingTask::create([
                'company_id' => $this->company_id,
                'process_id' => $this->id,
                'title' => $taskData['title'] ?? 'Görev '.($index + 1),
                'description' => $taskData['description'] ?? null,
                'type' => $taskData['type'] ?? 'custom',
                'order' => $index,
                'is_required' => $taskData['is_required'] ?? true,
                'due_date' => isset($taskData['days_offset'])
                    ? $this->start_date->addDays($taskData['days_offset'])
                    : null,
                'assigned_to' => $taskData['assigned_to'] ?? $this->assigned_to,
            ]);
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
