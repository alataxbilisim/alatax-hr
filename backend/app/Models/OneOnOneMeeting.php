<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OneOnOneMeeting extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'manager_id',
        'employee_id',
        'scheduled_at',
        'completed_at',
        'duration_minutes',
        'location',
        'meeting_link',
        'status',
        'agenda',
        'notes',
        'action_items',
        'talking_points',
        'mood',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_minutes' => 'integer',
        'action_items' => 'array',
        'talking_points' => 'array',
    ];

    const STATUS_SCHEDULED = 'scheduled';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_RESCHEDULED = 'rescheduled';

    const MOOD_VERY_NEGATIVE = 'very_negative';

    const MOOD_NEGATIVE = 'negative';

    const MOOD_NEUTRAL = 'neutral';

    const MOOD_POSITIVE = 'positive';

    const MOOD_VERY_POSITIVE = 'very_positive';

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Planlandı',
            self::STATUS_COMPLETED => 'Tamamlandı',
            self::STATUS_CANCELLED => 'İptal Edildi',
            self::STATUS_RESCHEDULED => 'Yeniden Planlandı',
        ];
    }

    public static function getMoodLabels(): array
    {
        return [
            self::MOOD_VERY_NEGATIVE => 'Çok Olumsuz',
            self::MOOD_NEGATIVE => 'Olumsuz',
            self::MOOD_NEUTRAL => 'Nötr',
            self::MOOD_POSITIVE => 'Olumlu',
            self::MOOD_VERY_POSITIVE => 'Çok Olumlu',
        ];
    }

    public static function getMoodEmojis(): array
    {
        return [
            self::MOOD_VERY_NEGATIVE => '😢',
            self::MOOD_NEGATIVE => '😕',
            self::MOOD_NEUTRAL => '😐',
            self::MOOD_POSITIVE => '🙂',
            self::MOOD_VERY_POSITIVE => '😊',
        ];
    }

    // Relationships
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForManager($query, int $managerId)
    {
        return $query->where('manager_id', $managerId);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // Methods
    public function complete(?string $notes = null, ?array $actionItems = null, ?string $mood = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'notes' => $notes ?? $this->notes,
            'action_items' => $actionItems ?? $this->action_items,
            'mood' => $mood ?? $this->mood,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function reschedule(\DateTime $newDate): void
    {
        $this->update([
            'status' => self::STATUS_RESCHEDULED,
            'scheduled_at' => $newDate,
        ]);
    }

    public function isPast(): bool
    {
        return $this->scheduled_at < now();
    }
}
