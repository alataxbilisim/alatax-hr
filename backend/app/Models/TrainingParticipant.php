<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'status',
        'score',
        'passed',
        'feedback',
        'registered_at',
        'completed_at',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'score' => 'integer',
        'registered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Eğitim oturumu
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    /**
     * Kullanıcı
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sertifika
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(TrainingCertificate::class, 'participant_id');
    }

    /**
     * Katılanlar
     */
    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    /**
     * Başarılı olanlar
     */
    public function scopePassed($query)
    {
        return $query->where('passed', true);
    }
}

