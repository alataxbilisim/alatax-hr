<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    // Workflow durumları
    const WORKFLOW_PENDING = 'pending';
    const WORKFLOW_IN_PROGRESS = 'in_progress';
    const WORKFLOW_COMPLETED = 'completed';
    const WORKFLOW_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'document_path',
        'document_name',
        'approved_by',
        'approved_at',
        'approval_note',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'approval_workflow_id',
        'current_step',
        'workflow_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'current_step' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvalWorkflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }

    public function approvalRecords(): MorphMany
    {
        return $this->morphMany(ApprovalRecord::class, 'approvable');
    }

    /**
     * Workflow tamamlandığında çağrılır
     */
    public function onWorkflowCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'workflow_status' => self::WORKFLOW_COMPLETED,
            'approved_at' => now(),
        ]);

        // Bakiyeyi güncelle
        $balance = LeaveBalance::where('user_id', $this->user_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->start_date->year)
            ->first();

        if ($balance) {
            $balance->approvePending($this->total_days);
        }
    }

    /**
     * Workflow reddedildiğinde çağrılır
     */
    public function onWorkflowRejected(string $reason, int $rejecterId): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'workflow_status' => self::WORKFLOW_REJECTED,
            'rejected_by' => $rejecterId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Bekleyen bakiyeyi geri al
        $balance = LeaveBalance::where('user_id', $this->user_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->start_date->year)
            ->first();

        if ($balance) {
            $balance->rejectPending($this->total_days);
        }
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    // Methods
    public function approve(int $approverId, ?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
            'approval_note' => $note,
        ]);

        // Update balance
        $balance = LeaveBalance::where('user_id', $this->user_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->start_date->year)
            ->first();

        if ($balance) {
            $balance->approvePending($this->total_days);
        }
    }

    public function reject(int $rejecterId, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_by' => $rejecterId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Update balance
        $balance = LeaveBalance::where('user_id', $this->user_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->start_date->year)
            ->first();

        if ($balance) {
            $balance->rejectPending($this->total_days);
        }
    }

    public function cancel(): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $balance = LeaveBalance::where('user_id', $this->user_id)
                ->where('leave_type_id', $this->leave_type_id)
                ->where('year', $this->start_date->year)
                ->first();

            if ($balance) {
                $balance->rejectPending($this->total_days);
            }
        }

        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Onay Bekliyor',
            self::STATUS_APPROVED => 'Onaylandı',
            self::STATUS_REJECTED => 'Reddedildi',
            self::STATUS_CANCELLED => 'İptal Edildi',
        ];
    }
}

