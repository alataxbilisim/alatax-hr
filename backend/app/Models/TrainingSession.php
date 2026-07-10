<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'start_date',
        'end_date',
        'location',
        'instructor',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Eğitim
     */
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    /**
     * Katılımcılar
     */
    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class, 'session_id');
    }

    /**
     * Oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Planlanmış oturumlar
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Tamamlanmış oturumlar
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Yaklaşan oturumlar
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->where('start_date', '>', now())
            ->orderBy('start_date');
    }

    /**
     * Mevcut katılımcı sayısı
     */
    public function getCurrentParticipantsCountAttribute(): int
    {
        return $this->participants()->count();
    }

    /**
     * Boş yer var mı?
     */
    public function hasAvailableSlots(): bool
    {
        $maxParticipants = $this->training->max_participants;

        if (! $maxParticipants) {
            return true;
        }

        return $this->current_participants_count < $maxParticipants;
    }
}
