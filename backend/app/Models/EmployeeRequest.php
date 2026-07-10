<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeRequest extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

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

    /**
     * Personel
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Talep tipi
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /**
     * Onaylayan
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Geçmiş kayıtları
     */
    public function history(): HasMany
    {
        return $this->hasMany(EmployeeRequestHistory::class)->orderByDesc('created_at');
    }

    /**
     * Durum etiketi
     */
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

    /**
     * Öncelik etiketi
     */
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

    /**
     * Beklemede mi?
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Onayla
     */
    public function approve(?string $note = null): void
    {
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
     * Reddet
     */
    public function reject(string $reason): void
    {
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

    /**
     * İptal et
     */
    public function cancel(): void
    {
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

    /**
     * Bekleyen talepler
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Onay bekleyen
     */
    public function scopeAwaitingApproval($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_REVIEW]);
    }
}

