<?php

namespace App\Services\Leaves;

use App\Enums\LeaveRequestStatus;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * İzin iptal — pending (sahip/İK) veya approved (İK cancel izni) + bakiye + onay motoru.
 */
class LeaveRequestCancelService
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    public function cancel(LeaveRequest $leaveRequest, User $actor): LeaveRequest
    {
        $status = $leaveRequest->status instanceof LeaveRequestStatus
            ? $leaveRequest->status
            : LeaveRequestStatus::tryFrom((string) $leaveRequest->status);

        if ($status === LeaveRequestStatus::Pending) {
            return $this->cancelPending($leaveRequest, $actor);
        }

        if ($status === LeaveRequestStatus::Approved) {
            return $this->cancelApproved($leaveRequest, $actor);
        }

        throw ValidationException::withMessages([
            'status' => ['Bu durumdaki izin talebi iptal edilemez.'],
        ]);
    }

    private function cancelPending(LeaveRequest $leaveRequest, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor) {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);

            if ($locked->status !== LeaveRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Sadece bekleyen talepler bu yolla iptal edilebilir.'],
                ]);
            }

            $days = (float) $locked->total_days;
            $approverIds = $this->closeOpenWorkflow($locked);

            $balance = $this->findBalance($locked);
            if ($balance) {
                $balance->rejectPending($days);
            }

            // workflow_status CHECK: pending|in_progress|completed|rejected — cancelled yok
            LeaveRequest::withoutAuditing(fn () => $locked->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'workflow_status' => LeaveRequest::WORKFLOW_REJECTED,
            ]));

            $this->notifyCancelled($locked->fresh(), $actor, $approverIds);

            return $locked->fresh(['leaveType', 'user']);
        });
    }

    private function cancelApproved(LeaveRequest $leaveRequest, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor) {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);

            if ($locked->status !== LeaveRequestStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['Talep onaylı değil.'],
                ]);
            }

            $days = (float) $locked->total_days;
            $this->closeOpenWorkflow($locked);

            $balance = $this->findBalance($locked);
            if ($balance) {
                $balance->restoreUsed($days);
            }

            LeaveRequest::withoutAuditing(fn () => $locked->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
            ]));

            $this->notifyCancelled($locked->fresh(), $actor, []);

            return $locked->fresh(['leaveType', 'user']);
        });
    }

    /**
     * Açık instance kapat + pending kayıtları skip.
     *
     * @return list<int> bildirilecek onaycı id'leri
     */
    private function closeOpenWorkflow(LeaveRequest $leaveRequest): array
    {
        $approverIds = ApprovalRecord::query()
            ->where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->whereNotNull('approver_id')
            ->pluck('approver_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        ApprovalInstance::query()
            ->where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
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
            ->where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->update([
                'status' => ApprovalRecord::STATUS_SKIPPED,
                'comment' => 'Talep iptal edildi — onay adımı atlandı',
                'decided_at' => now(),
                'is_current' => false,
            ]);

        return $approverIds;
    }

    private function findBalance(LeaveRequest $leaveRequest): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('company_id', $leaveRequest->company_id)
            ->where('user_id', $leaveRequest->user_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $leaveRequest->start_date->year)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  list<int>  $approverIds
     */
    private function notifyCancelled(LeaveRequest $leaveRequest, User $actor, array $approverIds): void
    {
        $companyId = (int) $leaveRequest->company_id;
        $payload = [
            'company_id' => $companyId,
            'entity' => __('messages.notifications.entity_leave'),
            'user' => $leaveRequest->user?->name ?? '',
            'date' => now()->toDateString(),
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $leaveRequest->id,
            'path' => '/leaves',
            'cancelled_by' => $actor->name,
        ];

        foreach ($approverIds as $approverId) {
            if ($approverId === (int) $actor->id) {
                continue;
            }
            $approver = User::query()->find($approverId);
            if ($approver && (int) $approver->company_id === $companyId) {
                $this->notifications->notify($approver, 'leave.cancelled', [
                    ...$payload,
                    'panel' => 'company',
                ]);
            }
        }

        $owner = $leaveRequest->user;
        if ($owner && (int) $owner->id !== (int) $actor->id && (int) $owner->company_id === $companyId) {
            $this->notifications->notify($owner, 'leave.cancelled', [
                ...$payload,
                'panel' => 'portal',
                'path' => '/leaves',
            ]);
        }
    }
}
