<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_id',
        'certificate_number',
        'issue_date',
        'expiry_date',
        'file_path',
        'issued_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * Katılımcı
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'participant_id');
    }

    /**
     * Veren kişi
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Süresi dolmuş mu?
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Geçerli sertifikalar
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>', now());
        });
    }

    /**
     * Süresi dolmuş sertifikalar
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now());
    }
}
