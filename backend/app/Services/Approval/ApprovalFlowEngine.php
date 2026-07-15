<?php

namespace App\Services\Approval;

use App\Events\ApprovalRequested;
use App\Models\ActivityLog;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Services\ApprovalStepConditionEvaluator;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Paralel grup + sıralı adım ilerletme (B4).
 * parallel_group NULL → tek adım (B0–B3 davranışı).
 */
class ApprovalFlowEngine
{
    public function __construct(
        protected ApprovalStepConditionEvaluator $evaluator,
        protected NotificationService $notifications,
    ) {}

    /**
     * @param  list<ApprovalStep>  $skippedBefore
     * @return list<ApprovalRecord>
     */
    public function openWave(
        ApprovalInstance $instance,
        ApprovalWorkflow $workflow,
        Model $approvable,
        array $context,
        ApprovalStep $anchorStep,
        array $skippedBefore = [],
    ): array {
        [$wave, $skippedInWave] = $this->resolveWaveFromAnchor($workflow, $anchorStep, $approvable, $context);

        foreach (array_merge($skippedBefore, $skippedInWave) as $skipped) {
            $this->createSkippedRecord($instance, $workflow, $approvable, $skipped, 'Koşul tutmadı — adım atlandı');
        }

        $records = [];
        $minOrder = null;

        foreach ($wave as $step) {
            $approver = $step->findApprover($approvable);
            $record = ApprovalRecord::create([
                'company_id' => $instance->company_id,
                'approval_instance_id' => $instance->id,
                'approval_workflow_id' => $workflow->id,
                'approval_step_id' => $step->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'approver_id' => $approver?->id,
                'status' => ApprovalRecord::STATUS_PENDING,
                'step_order' => $step->step_order,
                'is_current' => true,
            ]);

            $records[] = $record;
            $minOrder = $minOrder === null ? $step->step_order : min($minOrder, $step->step_order);

            if ($approver) {
                event(new ApprovalRequested($record, $approver, $approvable, $step));
            }
        }

        if ($minOrder !== null) {
            $instance->markInProgress($minOrder);
            if (in_array('current_step', $approvable->getFillable(), true)) {
                $approvable->update(['current_step' => $minOrder]);
            }
        }

        return $records;
    }

    /**
     * Onay sonrası: grup tamamsa sonraki dalga / tamamla.
     */
    public function afterApproval(ApprovalRecord $record): void
    {
        $record->loadMissing(['step', 'workflow', 'instance', 'approvable']);

        $step = $record->step;
        $workflow = $record->workflow;
        $instance = $record->instance;
        $approvable = $record->approvable;

        if (! $step || ! $workflow || ! $instance || ! $approvable) {
            return;
        }

        if ($step->parallel_group === null) {
            $this->advancePastOrder($record, (int) $record->step_order);

            return;
        }

        $policy = $step->completion_policy ?: ApprovalStep::COMPLETION_ALL;
        $groupId = (int) $step->parallel_group;

        $siblings = $this->currentPendingInGroup($instance, $workflow, $groupId);

        if ($policy === ApprovalStep::COMPLETION_ANY) {
            foreach ($siblings as $sibling) {
                if ((int) $sibling->id === (int) $record->id) {
                    continue;
                }
                $this->autoSkipSibling($sibling, 'Paralel grup (any): diğer onay yeterli — otomatik atlandı');
            }
            $maxOrder = $this->groupMaxOrder($workflow, $groupId);
            $this->advancePastOrder($record, $maxOrder);

            return;
        }

        // all
        if ($siblings->isNotEmpty()) {
            return; // hâlâ bekleyen var
        }

        $maxOrder = $this->groupMaxOrder($workflow, $groupId);
        $this->advancePastOrder($record, $maxOrder);
    }

    /**
     * Red: aynı instance'daki diğer pending current kayıtları kapat.
     */
    public function afterRejection(ApprovalRecord $record): void
    {
        $record->loadMissing(['instance']);
        $instance = $record->instance;
        if (! $instance) {
            return;
        }

        ApprovalRecord::query()
            ->where('approval_instance_id', $instance->id)
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->where('id', '!=', $record->id)
            ->get()
            ->each(function (ApprovalRecord $sibling) use ($record): void {
                $sibling->update([
                    'status' => ApprovalRecord::STATUS_SKIPPED,
                    'comment' => 'Paralel grup reddi nedeniyle kapatıldı',
                    'decided_at' => now(),
                    'is_current' => false,
                ]);

                ActivityLog::log(
                    'skip',
                    $sibling->approvable,
                    'Paralel grup reddi — kalan adım kapatıldı',
                    null,
                    ['step' => $sibling->step_order, 'closed_by_reject_of' => $record->id]
                );
            });
    }

    /**
     * Skip sonrası (manuel): onay gibi grup tamamını kontrol et.
     */
    public function afterSkip(ApprovalRecord $record): void
    {
        $this->afterApproval($record);
    }

    /**
     * @return array{0: list<ApprovalStep>, 1: list<ApprovalStep>}
     */
    public function resolveWaveFromAnchor(
        ApprovalWorkflow $workflow,
        ApprovalStep $anchor,
        Model $approvable,
        array $context,
    ): array {
        if ($anchor->parallel_group === null) {
            return [[$anchor], []];
        }

        $groupId = (int) $anchor->parallel_group;
        $wave = [];
        $skipped = [];

        $siblings = $workflow->steps()
            ->where('parallel_group', $groupId)
            ->orderBy('step_order')
            ->get();

        foreach ($siblings as $step) {
            if ($this->evaluator->matches($step->condition, $approvable, $context)) {
                $wave[] = $step;
            } else {
                $skipped[] = $step;
            }
        }

        if ($wave === []) {
            return [[$anchor], $skipped];
        }

        return [$wave, $skipped];
    }

    /**
     * İlk eşleşen adımı bul (koşul tutmayanlar skip listesine).
     *
     * @return array{0: ?ApprovalStep, 1: list<ApprovalStep>}
     */
    public function findFirstMatchingStep(ApprovalWorkflow $workflow, Model $approvable, array $context): array
    {
        $skipped = [];
        foreach ($workflow->steps()->orderBy('step_order')->get() as $step) {
            if ($this->evaluator->matches($step->condition, $approvable, $context)) {
                return [$step, $skipped];
            }
            $skipped[] = $step;
        }

        return [null, $skipped];
    }

    protected function advancePastOrder(ApprovalRecord $source, int $afterOrder): void
    {
        $approvable = $source->approvable;
        $workflow = $source->workflow;
        $instance = $source->instance;

        if (! $approvable || ! $workflow || ! $instance) {
            return;
        }

        $context = $this->buildContext($approvable);
        [$nextAnchor, $skipped] = $this->findNextMatchingAfter($workflow, $afterOrder, $approvable, $context);

        if ($nextAnchor === null) {
            foreach ($skipped as $skippedStep) {
                $this->createSkippedRecord(
                    $instance,
                    $workflow,
                    $approvable,
                    $skippedStep,
                    'Koşul tutmadı — adım atlandı'
                );
            }

            $instance->markApproved();

            if (method_exists($approvable, 'onWorkflowCompleted')) {
                $approvable->onWorkflowCompleted((int) $source->approver_id);
            }

            $this->notifications->notifyWorkflowOutcome($approvable, 'approved');

            return;
        }

        $this->openWave($instance, $workflow, $approvable, $context, $nextAnchor, $skipped);
    }

    /**
     * @return array{0: ?ApprovalStep, 1: list<ApprovalStep>}
     */
    protected function findNextMatchingAfter(
        ApprovalWorkflow $workflow,
        int $afterOrder,
        Model $approvable,
        array $context,
    ): array {
        $skipped = [];
        $order = $afterOrder;

        while (true) {
            $candidate = $workflow->getNextStep($order);
            if (! $candidate) {
                return [null, $skipped];
            }

            if (! $this->evaluator->matches($candidate->condition, $approvable, $context)) {
                $skipped[] = $candidate;
                $order = $candidate->step_order;

                continue;
            }

            return [$candidate, $skipped];
        }
    }

    protected function currentPendingInGroup(
        ApprovalInstance $instance,
        ApprovalWorkflow $workflow,
        int $groupId,
    ): Collection {
        $stepIds = $workflow->steps()
            ->where('parallel_group', $groupId)
            ->pluck('id');

        return ApprovalRecord::query()
            ->where('approval_instance_id', $instance->id)
            ->whereIn('approval_step_id', $stepIds)
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->get();
    }

    protected function groupMaxOrder(ApprovalWorkflow $workflow, int $groupId): int
    {
        return (int) $workflow->steps()
            ->where('parallel_group', $groupId)
            ->max('step_order');
    }

    protected function autoSkipSibling(ApprovalRecord $sibling, string $reason): void
    {
        $sibling->update([
            'status' => ApprovalRecord::STATUS_SKIPPED,
            'comment' => $reason,
            'decided_at' => now(),
            'is_current' => false,
        ]);

        ActivityLog::log(
            'skip',
            $sibling->approvable,
            $reason,
            null,
            ['step' => $sibling->step_order, 'parallel_auto_skip' => true]
        );
    }

    protected function createSkippedRecord(
        ApprovalInstance $instance,
        ApprovalWorkflow $workflow,
        Model $approvable,
        ApprovalStep $step,
        string $comment,
    ): void {
        ApprovalRecord::create([
            'company_id' => $instance->company_id,
            'approval_instance_id' => $instance->id,
            'approval_workflow_id' => $workflow->id,
            'approval_step_id' => $step->id,
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->id,
            'approver_id' => null,
            'status' => ApprovalRecord::STATUS_SKIPPED,
            'comment' => $comment,
            'decided_at' => now(),
            'step_order' => $step->step_order,
            'is_current' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildContext(Model $approvable): array
    {
        return [
            'total_days' => $approvable->getAttribute('total_days'),
            'leave_type_id' => $approvable->getAttribute('leave_type_id'),
            'user_id' => $approvable->getAttribute('user_id'),
            'requester_id' => $approvable->getAttribute('user_id'),
            'amount' => $approvable->getAttribute('amount')
                ?? $approvable->getAttribute('total_amount'),
            'total_amount' => $approvable->getAttribute('total_amount'),
            'department_id' => $approvable->getAttribute('department_id'),
        ];
    }
}
