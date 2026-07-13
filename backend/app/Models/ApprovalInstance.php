<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Çalışan onay örneği (polymorphic) — entity status ile senkron tutulur.
 */
class ApprovalInstance extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'approval_workflow_id',
        'approvable_type',
        'approvable_id',
        'current_step',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function records(): HasMany
    {
        return $this->hasMany(ApprovalRecord::class);
    }

    public function markInProgress(int $stepOrder): void
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'current_step' => $stepOrder,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markApproved(): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'completed_at' => now(),
        ]);
    }

    public function markRejected(): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'completed_at' => now(),
        ]);
    }
}
