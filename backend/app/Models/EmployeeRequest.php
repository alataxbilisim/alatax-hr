<?php

namespace App\Models;

use App\Services\WorkflowService;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class EmployeeRequest extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'request_type_id',
        'title',
        'description',
        'form_data',
        'status',
        'rejection_reason',
        'attachments',
        'approved_by',
        'approved_at',
        'priority',
        'effective_date',
        'due_date',
        'notes',
        'admin_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'form_data' => 'array',
        'attachments' => 'array',
        'approved_at' => 'datetime',
        'effective_date' => 'date',
        'due_date' => 'date',
    ];

    const STATUS_PENDING = 'pending';

    const STATUS_IN_REVIEW = 'in_review';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const PRIORITY_LOW = 'low';

    const PRIORITY_NORMAL = 'normal';

    const PRIORITY_HIGH = 'high';

    const PRIORITY_URGENT = 'urgent';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EmployeeRequestHistory::class)->orderByDesc('created_at');
    }

    public function approvalRecords(): MorphMany
    {
        return $this->morphMany(ApprovalRecord::class, 'approvable');
    }

    public function getStatusLabelAttribute(): string
    {
        $statuses = [
            self::STATUS_PENDING => 'Beklemede',
            self::STATUS_IN_REVIEW => 'İnceleniyor',
            self::STATUS_APPROVED => 'Onaylandı',
            self::STATUS_REJECTED => 'Reddedildi',
            self::STATUS_CANCELLED => 'İptal Edildi',
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        $priorities = [
            self::PRIORITY_LOW => 'Düşük',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'Yüksek',
            self::PRIORITY_URGENT => 'Acil',
        ];

        return $priorities[$this->priority] ?? $this->priority;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Motor tamamlandığında (tek yol — yeni kayıtlar).
     */
    public function onWorkflowCompleted(?int $approverId = null): void
    {
        $oldStatus = $this->status;
        $actorId = $approverId ?? auth()->id();

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $actorId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->history()->create([
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_APPROVED,
            'comment' => 'Onay akışı tamamlandı',
            'changed_by' => $actorId,
        ]);
    }

    /**
     * Motor reddi.
     */
    public function onWorkflowRejected(string $reason, int $rejecterId): void
    {
        $oldStatus = $this->status;

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => $rejecterId,
            'approved_at' => now(),
        ]);

        $this->history()->create([
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_REJECTED,
            'comment' => $reason,
            'changed_by' => $rejecterId,
        ]);
    }

    /**
     * Geçiş verisi (instance yok): legacy doğrudan onay.
     * Açık instance varken ÇAĞRILMAZ — çift onay engeli.
     */
    public function approve(?string $note = null): void
    {
        if (app(WorkflowService::class)->hasOpenInstance($this)) {
            throw new RuntimeException(
                'Bu talep onay motoru üzerinden sonuçlandırılmalıdır (açık approval_instance var).'
            );
        }

        $oldStatus = $this->status;

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->history()->create([
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_APPROVED,
            'comment' => $note,
            'changed_by' => auth()->id(),
        ]);
    }

    /**
     * Geçiş verisi (instance yok): legacy red.
     */
    public function reject(string $reason): void
    {
        if (app(WorkflowService::class)->hasOpenInstance($this)) {
            throw new RuntimeException(
                'Bu talep onay motoru üzerinden sonuçlandırılmalıdır (açık approval_instance var).'
            );
        }

        $oldStatus = $this->status;

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->history()->create([
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_REJECTED,
            'comment' => $reason,
            'changed_by' => auth()->id(),
        ]);
    }

    public function cancel(): void
    {
        app(WorkflowService::class)->cancelOpenInstances($this);

        $oldStatus = $this->status;

        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        $this->history()->create([
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_CANCELLED,
            'comment' => 'Talep iptal edildi',
            'changed_by' => auth()->id(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAwaitingApproval($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_REVIEW]);
    }
}
