<?php

namespace App\Models;

use App\Services\Approval\ApprovalFlowEngine;
use App\Services\Notification\NotificationService;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

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

    /**
     * @return bool true = karar işlendi; false = yarış/idempotent no-op
     */
    public function approve(?string $comment = null, ?int $actingApproverId = null): bool
    {
        return (bool) DB::transaction(function () use ($comment, $actingApproverId): bool {
            if ($this->approval_instance_id) {
                ApprovalInstance::query()->whereKey($this->approval_instance_id)->lockForUpdate()->first();
            }

            $this->refresh();

            if ($this->status !== self::STATUS_PENDING || ! $this->is_current) {
                return false;
            }

            $payload = [
                'status' => self::STATUS_APPROVED,
                'comment' => $comment,
                'decided_at' => now(),
                'is_current' => false,
            ];

            if ($actingApproverId !== null) {
                $payload['approver_id'] = $actingApproverId;
            }

            $this->update($payload);
            $this->loadMissing('step');

            ActivityLog::log(
                'approve',
                $this->approvable,
                'Onay adımı tamamlandı: '.($this->step?->name ?? '#' . $this->step_order),
                null,
                ['step' => $this->step_order, 'comment' => $comment]
            );

            app(ApprovalFlowEngine::class)->afterApproval(
                $this->fresh(['step', 'workflow', 'instance', 'approvable'])
            );

            return true;
        });
    }

    public function reject(string $reason, ?int $actingApproverId = null): bool
    {
        return (bool) DB::transaction(function () use ($reason, $actingApproverId): bool {
            if ($this->approval_instance_id) {
                ApprovalInstance::query()->whereKey($this->approval_instance_id)->lockForUpdate()->first();
            }

            $this->refresh();

            if ($this->status !== self::STATUS_PENDING || ! $this->is_current) {
                return false;
            }

            $payload = [
                'status' => self::STATUS_REJECTED,
                'comment' => $reason,
                'decided_at' => now(),
                'is_current' => false,
            ];

            if ($actingApproverId !== null) {
                $payload['approver_id'] = $actingApproverId;
            }

            $this->update($payload);

            app(ApprovalFlowEngine::class)->afterRejection($this);

            $this->instance?->markRejected();

            $approvable = $this->approvable;
            if ($approvable && method_exists($approvable, 'onWorkflowRejected')) {
                $approvable->onWorkflowRejected($reason, (int) ($actingApproverId ?? $this->approver_id));
            }

            ActivityLog::log(
                'reject',
                $approvable,
                "Talep reddedildi: {$reason}",
                null,
                ['step' => $this->step_order, 'reason' => $reason]
            );

            if ($approvable) {
                app(NotificationService::class)
                    ->notifyWorkflowOutcome($approvable, 'rejected', $reason);
            }

            return true;
        });
    }

    public function skip(?string $reason = null, ?int $actingApproverId = null): bool
    {
        return (bool) DB::transaction(function () use ($reason, $actingApproverId): bool {
            if ($this->approval_instance_id) {
                ApprovalInstance::query()->whereKey($this->approval_instance_id)->lockForUpdate()->first();
            }

            $this->refresh();

            if ($this->status !== self::STATUS_PENDING || ! $this->is_current) {
                return false;
            }

            $payload = [
                'status' => self::STATUS_SKIPPED,
                'comment' => $reason ?? 'Adım atlandı',
                'decided_at' => now(),
                'is_current' => false,
            ];

            if ($actingApproverId !== null) {
                $payload['approver_id'] = $actingApproverId;
            }

            $this->update($payload);

            app(ApprovalFlowEngine::class)->afterSkip(
                $this->fresh(['step', 'workflow', 'instance', 'approvable'])
            );

            return true;
        });
    }
}
