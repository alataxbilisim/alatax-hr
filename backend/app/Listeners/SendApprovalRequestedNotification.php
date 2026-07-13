<?php

namespace App\Listeners;

use App\Events\ApprovalRequested;
use App\Notifications\ApprovalRequestedNotification;

class SendApprovalRequestedNotification
{
    public function handle(ApprovalRequested $event): void
    {
        $event->approver->notify(new ApprovalRequestedNotification(
            $event->record,
            $event->approvable,
            $event->step,
        ));
    }
}
