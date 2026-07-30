<?php

namespace App\Models;

use App\Enums\DestructionCandidateStatus;
use App\Enums\RetentionStrategy;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestructionCandidate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'retention_policy_id',
        'subject_type',
        'subject_id',
        'data_category',
        'record_count',
        'due_since',
        'status',
        'strategy',
        'preview_snapshot',
        'approval_id',
        'skip_reason',
        'deferred_until',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'record_count' => 'integer',
        'due_since' => 'datetime',
        'status' => DestructionCandidateStatus::class,
        'strategy' => RetentionStrategy::class,
        'preview_snapshot' => 'array',
        'deferred_until' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'retention_policy_id');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(DestructionApproval::class, 'approval_id');
    }
}
