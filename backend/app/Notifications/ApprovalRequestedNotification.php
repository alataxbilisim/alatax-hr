<?php

namespace App\Notifications;

use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * @deprecated 4C-1: ApprovalRequested listener → NotificationService / CatalogNotification.
 * Stub tutulur; yeni kod NotificationService kullanır. via() boş — yanlışlıkla dispatch edilirse no-op.
 */
class ApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ApprovalRecord $record,
        public Model $approvable,
        public ApprovalStep $step,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_requested',
            'approval_record_id' => $this->record->id,
            'deprecated' => true,
        ];
    }
}
