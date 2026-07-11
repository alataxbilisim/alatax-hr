<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRecord;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkflowService
{
    /**
     * Bir talep için workflow başlat
     */
    public function startWorkflow(Model $approvable, array $context = []): ?ApprovalRecord
    {
        $entityType = $this->getEntityType($approvable);
        $companyId = $approvable->company_id;

        // Uygun workflow'u bul
        $workflow = ApprovalWorkflow::findForRequest($companyId, $entityType, $context);

        if (! $workflow) {
            // Workflow yoksa direkt onayla
            if (method_exists($approvable, 'onWorkflowCompleted')) {
                $approvable->onWorkflowCompleted();
            }

            return null;
        }

        // İlk adımı al
        $firstStep = $workflow->getFirstStep();
        if (! $firstStep) {
            return null;
        }

        // Onaylayıcıyı bul
        $approver = $firstStep->findApprover($approvable);

        // Talebe workflow bilgilerini ekle
        $approvable->update([
            'approval_workflow_id' => $workflow->id,
            'current_step' => $firstStep->step_order,
            'workflow_status' => 'in_progress',
        ]);

        // İlk onay kaydını oluştur
        $record = ApprovalRecord::create([
            'company_id' => $companyId,
            'approval_workflow_id' => $workflow->id,
            'approval_step_id' => $firstStep->id,
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->id,
            'approver_id' => $approver?->id,
            'status' => ApprovalRecord::STATUS_PENDING,
            'step_order' => $firstStep->step_order,
            'is_current' => true,
        ]);

        // Log kaydı
        ActivityLog::log(
            'workflow_started',
            $approvable,
            "Onay akışı başlatıldı: {$workflow->name}",
            null,
            ['workflow_id' => $workflow->id, 'approver_id' => $approver?->id]
        );

        // Onaylayıcıya bildirim gönder
        if ($approver) {
            $this->notifyApprover($approver, $approvable, $firstStep);
        }

        return $record;
    }

    /**
     * Onay işlemi
     */
    public function approve(ApprovalRecord $record, int $approverId, ?string $comment = null): bool
    {
        // Yetki kontrolü
        if (! $this->canApprove($record, $approverId)) {
            return false;
        }

        $record->update([
            'approver_id' => $approverId,
        ]);

        $record->approve($comment);

        return true;
    }

    /**
     * Red işlemi
     */
    public function reject(ApprovalRecord $record, int $approverId, string $reason): bool
    {
        // Yetki kontrolü
        if (! $this->canApprove($record, $approverId)) {
            return false;
        }

        $record->update([
            'approver_id' => $approverId,
        ]);

        $record->reject($reason);

        return true;
    }

    /**
     * Adım atlama
     */
    public function skip(ApprovalRecord $record, int $approverId, ?string $reason = null): bool
    {
        if (! $this->canApprove($record, $approverId)) {
            return false;
        }

        $step = $record->step;

        if (! $step || ! $step->can_skip) {
            return false;
        }

        $record->update([
            'approver_id' => $approverId,
        ]);

        $record->skip($reason);

        return true;
    }

    /**
     * Kullanıcının onaylayabileceği bekleyen kayıtları getir
     */
    public function getPendingApprovalsForUser(int $userId, int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        // Direkt atanan veya vekalet verilen onaylar
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
            ->with(['approvable', 'step', 'workflow'])
            ->get();
    }

    /**
     * Bir talebin onay geçmişini getir
     */
    public function getApprovalHistory(Model $approvable): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovalRecord::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->with(['step', 'approver'])
            ->orderBy('step_order')
            ->get();
    }

    /**
     * Kullanıcının onaylama yetkisi var mı?
     */
    public function canApprove(ApprovalRecord $record, int $userId): bool
    {
        if ($record->status !== ApprovalRecord::STATUS_PENDING) {
            return false;
        }

        if (! $record->is_current) {
            return false;
        }

        // super_admin type veya Spatie admin rolü (company_admin type tek başına yetmez)
        $actor = User::query()->find($userId);
        if ($actor && ($actor->type === UserType::SuperAdmin || $actor->hasRole('admin'))) {
            return true;
        }

        // Direkt atanmış mı?
        if ((int) $record->approver_id === $userId) {
            return true;
        }

        // Vekalet: entity_type leave_request / LeaveRequest / null (tümü)
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

    /**
     * Entity tipini belirle
     */
    protected function getEntityType(Model $approvable): string
    {
        $class = class_basename($approvable);

        $mapping = [
            'LeaveRequest' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
            'AssetRequest' => ApprovalWorkflow::ENTITY_ASSET_REQUEST,
            'ExpenseRequest' => ApprovalWorkflow::ENTITY_EXPENSE_REQUEST,
            'TrainingRequest' => ApprovalWorkflow::ENTITY_TRAINING_REQUEST,
            'Document' => ApprovalWorkflow::ENTITY_DOCUMENT_APPROVAL,
        ];

        return $mapping[$class] ?? strtolower($class);
    }

    /**
     * Onaylayıcıya bildirim gönder
     */
    protected function notifyApprover($approver, $approvable, $step): void
    {
        // TODO: Notification sistemi ile entegre et
        // $approver->notify(new ApprovalRequestNotification($approvable, $step));
    }
}
