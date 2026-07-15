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
 * Dosya geriye uyumluluk için tutulur; yeni kod NotificationService kullanır.
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
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_requested',
            'approval_record_id' => $this->record->id,
            'approval_step_id' => $this->step->id,
            'step_name' => $this->step->name,
            'approvable_type' => $this->record->approvable_type,
            'approvable_id' => $this->record->approvable_id,
            'message' => 'Onayınız bekleniyor: '.$this->step->name,
        ];
    }
}
