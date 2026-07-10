<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRecord extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'approval_workflow_id',
        'approval_step_id',
        'approvable_type',
        'approvable_id',
        'approver_id',
        'status',
        'comment',
        'decided_at',
        'step_order',
        'is_current',
        'escalated_at',
        'escalated_to',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'decided_at' => 'datetime',
        'escalated_at' => 'datetime',
        'step_order' => 'integer',
    ];

    // Durumlar
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_SKIPPED = 'skipped';

    const STATUS_ESCALATED = 'escalated';

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Bekliyor',
            self::STATUS_APPROVED => 'Onaylandı',
            self::STATUS_REJECTED => 'Reddedildi',
            self::STATUS_SKIPPED => 'Atlandı',
            self::STATUS_ESCALATED => 'Yükseltildi',
        ];
    }

    // Relationships
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeForApprover($query, int $userId)
    {
        return $query->where('approver_id', $userId);
    }

    // Methods
    public function approve(?string $comment = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'comment' => $comment,
            'decided_at' => now(),
            'is_current' => false,
        ]);

        // Log kaydı
        ActivityLog::log(
            'approve',
            $this->approvable,
            "Onay adımı tamamlandı: {$this->step->name}",
            null,
            ['step' => $this->step_order, 'comment' => $comment]
        );

        // Sonraki adıma geç veya tamamla
        $this->moveToNextStep();
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'comment' => $reason,
            'decided_at' => now(),
            'is_current' => false,
        ]);

        // Talebi reddet
        $approvable = $this->approvable;
        if (method_exists($approvable, 'onWorkflowRejected')) {
            $approvable->onWorkflowRejected($reason, $this->approver_id);
        }

        ActivityLog::log(
            'reject',
            $approvable,
            "Talep reddedildi: {$reason}",
            null,
            ['step' => $this->step_order, 'reason' => $reason]
        );
    }

    public function skip(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'comment' => $reason ?? 'Adım atlandı',
            'decided_at' => now(),
            'is_current' => false,
        ]);

        $this->moveToNextStep();
    }

    protected function moveToNextStep(): void
    {
        $approvable = $this->approvable;
        $workflow = $this->workflow;
        $nextStep = $workflow->getNextStep($this->step_order);

        if ($nextStep) {
            // Sonraki adım için kayıt oluştur
            $approver = $nextStep->findApprover($approvable);

            self::create([
                'company_id' => $this->company_id,
                'approval_workflow_id' => $workflow->id,
                'approval_step_id' => $nextStep->id,
                'approvable_type' => $this->approvable_type,
                'approvable_id' => $this->approvable_id,
                'approver_id' => $approver?->id,
                'status' => self::STATUS_PENDING,
                'step_order' => $nextStep->step_order,
                'is_current' => true,
            ]);

            // Talep durumunu güncelle
            $approvable->update(['current_step' => $nextStep->step_order]);

            // Onaylayıcıya bildirim gönder
            if ($approver) {
                // TODO: Notification gönder
            }
        } else {
            // Tüm adımlar tamamlandı
            if (method_exists($approvable, 'onWorkflowCompleted')) {
                $approvable->onWorkflowCompleted();
            }
        }
    }
}
