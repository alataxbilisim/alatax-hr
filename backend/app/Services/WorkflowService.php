<?php

namespace App\Services;

use App\Enums\UserType;
use App\Events\ApprovalRequested;
use App\Models\ActivityLog;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Services\Approval\ApprovalFlowEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkflowService
{
    public function __construct(
        protected ApprovalFlowEngine $flowEngine,
    ) {}

    /**
     * Bir talep için workflow başlat.
     * Akış yoksa legacy: null döner, entity pending kalır (otomatik onay YOK).
     * Paralel dalga: birden fazla pending açılır; dönüş = ilk (en düşük step_order) kayıt.
     */
    public function startWorkflow(Model $approvable, array $context = []): ?ApprovalRecord
    {
        $entityType = $this->getEntityType($approvable);
        $companyId = (int) $approvable->company_id;

        $workflow = ApprovalWorkflow::findForRequest($companyId, $entityType, $context);

        if (! $workflow) {
            Log::warning('approval.workflow.missing_legacy_fallback', [
                'company_id' => $companyId,
                'entity_type' => $entityType,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
            ]);

            return null;
        }

        if (! $workflow->getFirstStep()) {
            Log::warning('approval.workflow.empty_steps', [
                'workflow_id' => $workflow->id,
                'company_id' => $companyId,
            ]);

            return null;
        }

        [$resolvedFirst, $skippedSteps] = $this->flowEngine->findFirstMatchingStep(
            $workflow,
            $approvable,
            $context
        );

        if (! $resolvedFirst) {
            Log::warning('approval.workflow.no_matching_steps', [
                'workflow_id' => $workflow->id,
                'company_id' => $companyId,
            ]);

            return null;
        }

        $instance = ApprovalInstance::create([
            'company_id' => $companyId,
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->id,
            'current_step' => $resolvedFirst->step_order,
            'status' => ApprovalInstance::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        if ($this->approvableHasWorkflowColumns($approvable)) {
            $approvable->update([
                'approval_workflow_id' => $workflow->id,
                'current_step' => $resolvedFirst->step_order,
                'workflow_status' => 'in_progress',
            ]);
        }

        $records = $this->flowEngine->openWave(
            $instance,
            $workflow,
            $approvable,
            $context,
            $resolvedFirst,
            $skippedSteps
        );

        $record = $records[0] ?? null;

        ActivityLog::log(
            'workflow_started',
            $approvable,
            "Onay akışı başlatıldı: {$workflow->name}",
            null,
            [
                'workflow_id' => $workflow->id,
                'instance_id' => $instance->id,
                'approver_id' => $record?->approver_id,
                'opened_count' => count($records),
            ]
        );

        return $record;
    }

    /**
     * Reddedilmiş talebi yeniden gönder — YENİ instance; eski kayıtlar korunur.
     */
    public function resubmitWorkflow(Model $approvable, array $context = []): ?ApprovalRecord
    {
        ApprovalInstance::query()
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->whereIn('status', [
                ApprovalInstance::STATUS_PENDING,
                ApprovalInstance::STATUS_IN_PROGRESS,
            ])
            ->each(function (ApprovalInstance $instance): void {
                $instance->update([
                    'status' => ApprovalInstance::STATUS_CANCELLED,
                    'completed_at' => now(),
                ]);
            });

        ApprovalRecord::query()
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        if ($this->approvableHasWorkflowColumns($approvable)) {
            $approvable->update([
                'approval_workflow_id' => null,
                'current_step' => null,
                'workflow_status' => 'pending',
            ]);
        }

        return $this->startWorkflow($approvable, $context);
    }

    public function approve(ApprovalRecord $record, int $approverId, ?string $comment = null): bool
    {
        if (! $this->canApprove($record, $approverId)) {
            return false;
        }

        return $this->processAuthorizedApproval($record, $approverId, $comment);
    }

    /**
     * Policy zaten authorize etmiş köprü yolu (leave/expense approve).
     * Motor "kim" çözmüştü; Policy "onaylayabilir mi" dedi — canApprove tekrarlanmaz.
     */
    public function processAuthorizedApproval(ApprovalRecord $record, int $approverId, ?string $comment = null): bool
    {
        return $record->approve($comment, $approverId);
    }

    public function reject(ApprovalRecord $record, int $approverId, string $reason): bool
    {
        if (! $this->canApprove($record, $approverId)) {
            return false;
        }

        return $this->processAuthorizedRejection($record, $approverId, $reason);
    }

    public function processAuthorizedRejection(ApprovalRecord $record, int $approverId, string $reason): bool
    {
        return $record->reject($reason, $approverId);
    }

    public function skip(ApprovalRecord $record, int $approverId, ?string $reason = null): bool
    {
        if (! $this->canApprove($record, $approverId)) {
            return false;
        }

        $step = $record->step;

        if (! $step || ! $step->can_skip) {
            return false;
        }

        return $record->skip($reason, $approverId);
    }

    /**
     * Aktörün onaylayabileceği pending current kayıt (paralel grup güvenliği).
     * Yetkiyi GENİŞLETMEZ — yalnızca doğru kaydı seçer.
     */
    public function findPendingRecordForActor(Model $approvable, int $userId): ?ApprovalRecord
    {
        $records = ApprovalRecord::query()
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            if ($this->canApprove($record, $userId)) {
                return $record;
            }
        }

        return null;
    }

    public function getPendingApprovalsForUser(int $userId, int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        $delegatorIds = ApprovalDelegation::where('company_id', $companyId)
            ->where('delegate_id', $userId)
            ->active()
            ->pluck('delegator_id');

        return ApprovalRecord::where('company_id', $companyId)
            ->pending()
            ->current()
            ->where(function ($q) use ($userId, $delegatorIds) {
                $q->where('approver_id', $userId)
                    ->orWhereIn('approver_id', $delegatorIds);
            })
            ->with(['approvable', 'step', 'workflow', 'instance'])
            ->get();
    }

    public function getApprovalHistory(Model $approvable): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovalRecord::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->with(['step', 'approver'])
            ->orderBy('step_order')
            ->get();
    }

    public function canApprove(ApprovalRecord $record, int $userId): bool
    {
        if ($record->status !== ApprovalRecord::STATUS_PENDING) {
            return false;
        }

        if (! $record->is_current) {
            return false;
        }

        $actor = User::query()->find($userId);
        if ($actor && ($actor->type === UserType::SuperAdmin || $actor->hasRole('admin'))) {
            return true;
        }

        if ((int) $record->approver_id === $userId) {
            return true;
        }

        $entityHints = $this->delegationEntityHints($record);

        foreach ($entityHints as $entityType) {
            $delegate = ApprovalDelegation::findActiveDelegate(
                (int) $record->approver_id,
                (int) $record->company_id,
                $entityType
            );

            if ($delegate && (int) $delegate->id === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string|null>
     */
    protected function delegationEntityHints(ApprovalRecord $record): array
    {
        $hints = [null];

        if (! $record->relationLoaded('approvable')) {
            $record->load('approvable');
        }

        if (! $record->approvable) {
            return $hints;
        }

        $basename = class_basename($record->approvable);
        $hints[] = $basename;
        $hints[] = Str::snake($basename);

        return array_values(array_unique($hints));
    }

    protected function getEntityType(Model $approvable): string
    {
        $class = class_basename($approvable);

        $mapping = [
            'LeaveRequest' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'AssetRequest' => ApprovalWorkflow::ENTITY_ASSET_REQUEST,
            'ExpenseRequest' => ApprovalWorkflow::ENTITY_EXPENSE_REQUEST,
            'ExpenseClaim' => ApprovalWorkflow::ENTITY_EXPENSE_REQUEST,
            'TrainingRequest' => ApprovalWorkflow::ENTITY_TRAINING_REQUEST,
            'Document' => ApprovalWorkflow::ENTITY_DOCUMENT_APPROVAL,
        ];

        return $mapping[$class] ?? strtolower($class);
    }

    protected function notifyApprover(User $approver, Model $approvable, ApprovalStep $step, ApprovalRecord $record): void
    {
        event(new ApprovalRequested($record, $approver, $approvable, $step));
    }

    protected function approvableHasWorkflowColumns(Model $approvable): bool
    {
        return in_array('approval_workflow_id', $approvable->getFillable(), true)
            && in_array('workflow_status', $approvable->getFillable(), true);
    }
}
