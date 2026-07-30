<?php

namespace App\Models;

use App\Enums\RetentionDecisionType;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only karar kaydı — güncellenemez/silinemez. */
class RetentionDecision extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'destruction_candidate_id',
        'subject_type',
        'subject_id',
        'decision',
        'reason',
        'defer_until',
        'decided_by',
        'created_at',
    ];

    protected $casts = [
        'decision' => RetentionDecisionType::class,
        'subject_id' => 'integer',
        'defer_until' => 'date',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('retention_decisions append-only');
        });
        static::deleting(function (): void {
            throw new LogicException('retention_decisions append-only');
        });
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
