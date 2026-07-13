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
        'approval_instance_id',
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
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

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

        $this->instance?->markRejected();

        // Talebi reddet
        $approvable = $this->approvable;
        if (method_exists($approvable, 'onWorkflowRejected')) {
            $approvable->onWorkflowRejected($reason, (int) $this->approver_id);
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
        $instance = $this->instance;
        $evaluator = app(\App\Services\ApprovalStepConditionEvaluator::class);
        $context = [
            'total_days' => $approvable->getAttribute('total_days'),
            'leave_type_id' => $approvable->getAttribute('leave_type_id'),
            'user_id' => $approvable->getAttribute('user_id'),
            'requester_id' => $approvable->getAttribute('user_id'),
            'amount' => $approvable->getAttribute('amount'),
            'department_id' => $approvable->getAttribute('department_id'),
        ];

        $nextStep = null;
        $order = $this->step_order;

        while (true) {
            $candidate = $workflow->getNextStep($order);
            if (! $candidate) {
                break;
            }

            if ($evaluator->matches($candidate->condition, $approvable, $context)) {
                $nextStep = $candidate;
                break;
            }

            self::create([
                'company_id' => $this->company_id,
                'approval_instance_id' => $this->approval_instance_id,
                'approval_workflow_id' => $workflow->id,
                'approval_step_id' => $candidate->id,
                'approvable_type' => $this->approvable_type,
                'approvable_id' => $this->approvable_id,
                'approver_id' => null,
                'status' => self::STATUS_SKIPPED,
                'comment' => 'Koşul tutmadı — adım atlandı',
                'decided_at' => now(),
                'step_order' => $candidate->step_order,
                'is_current' => false,
            ]);

            $order = $candidate->step_order;
        }

        if ($nextStep) {
            $approver = $nextStep->findApprover($approvable);

            $nextRecord = self::create([
                'company_id' => $this->company_id,
                'approval_instance_id' => $this->approval_instance_id,
                'approval_workflow_id' => $workflow->id,
                'approval_step_id' => $nextStep->id,
                'approvable_type' => $this->approvable_type,
                'approvable_id' => $this->approvable_id,
                'approver_id' => $approver?->id,
                'status' => self::STATUS_PENDING,
                'step_order' => $nextStep->step_order,
                'is_current' => true,
            ]);

            $instance?->markInProgress($nextStep->step_order);

            if (in_array('current_step', $approvable->getFillable(), true)) {
                $approvable->update(['current_step' => $nextStep->step_order]);
            }

            if ($approver) {
                event(new \App\Events\ApprovalRequested($nextRecord, $approver, $approvable, $nextStep));
            }
        } else {
            $instance?->markApproved();

            if (method_exists($approvable, 'onWorkflowCompleted')) {
                $approvable->onWorkflowCompleted((int) $this->approver_id);
            }
        }
    }
}
