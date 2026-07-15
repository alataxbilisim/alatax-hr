<?php

namespace App\Listeners;

use App\Events\ApprovalRequested;
use App\Models\ApprovalStep;
use App\Services\Notification\NotificationService;

/**
 * ApprovalRequested → NotificationService (in-app + queued mail).
 */
class SendApprovalRequestedNotification
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    public function handle(ApprovalRequested $event): void
    {
        $base = null;
        $step = $event->step;
        if ($step instanceof ApprovalStep) {
            $base = $step->resolveBaseApprover($event->approvable);
            // Vekalet: event.approver genelde vekil; asıl onaycıyı da bilgilendir
            if ($base === null) {
                $base = $event->approver;
            }
        }

        $this->notifications->notifyApprovalRequested(
            $event->record,
            $event->approver,
            $event->approvable,
            $event->step,
            $base,
        );
    }
}
