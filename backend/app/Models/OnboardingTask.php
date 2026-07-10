<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingTask extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

    const TYPE_DOCUMENT_UPLOAD = 'document_upload';
    const TYPE_DOCUMENT_FILL = 'document_fill';
    const TYPE_TRAINING = 'training';
    const TYPE_MEETING = 'meeting';
    const TYPE_SYSTEM_SETUP = 'system_setup';
    const TYPE_QUIZ = 'quiz';
    const TYPE_CUSTOM = 'custom';

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'company_id',
        'process_id',
        'title',
        'description',
        'type',
        'order',
        'is_required',
        'due_date',
        'status',
        'completed_at',
        'completed_by',
        'data',
        'notes',
        'assigned_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'data' => 'array',
    ];

    // Relationships
    public function process()
    {
        return $this->belongsTo(OnboardingProcess::class, 'process_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Methods
    public function complete(int $userId, ?array $data = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $userId,
            'data' => $data,
        ]);

        $this->process->updateProgress();
    }

    public function skip(): void
    {
        if (!$this->is_required) {
            $this->update(['status' => self::STATUS_SKIPPED]);
            $this->process->updateProgress();
        }
    }

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_DOCUMENT_UPLOAD => 'Evrak Yükleme',
            self::TYPE_DOCUMENT_FILL => 'Form Doldurma',
            self::TYPE_TRAINING => 'Eğitim',
            self::TYPE_MEETING => 'Toplantı',
            self::TYPE_SYSTEM_SETUP => 'Sistem Kurulumu',
            self::TYPE_QUIZ => 'Quiz/Anket',
            self::TYPE_CUSTOM => 'Özel Görev',
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Bekliyor',
            self::STATUS_IN_PROGRESS => 'Devam Ediyor',
            self::STATUS_COMPLETED => 'Tamamlandı',
            self::STATUS_SKIPPED => 'Atlandı',
        ];
    }
}

