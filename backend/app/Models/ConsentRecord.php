<?php

namespace App\Models;

use App\Enums\KvkkConsentType;
use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rıza kanıt kaydı — soft delete yok; geri çekme withdrawn_at ile.
 */
class ConsentRecord extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'subject_type',
        'subject_id',
        'notice_id',
        'consent_type',
        'granted',
        'granted_at',
        'withdrawn_at',
        'source',
        'ip',
        'user_agent',
        'evidence',
    ];

    protected $casts = [
        'consent_type' => KvkkConsentType::class,
        'granted' => 'boolean',
        'granted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'evidence' => 'array',
        'subject_id' => 'integer',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(PrivacyNotice::class, 'notice_id');
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }
}
