<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel database kanalı + company_id (tenant denetim izi).
 */
class TenantDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        $payload = parent::buildPayload($notifiable, $notification);

        $companyId = null;
        if (isset($notifiable->company_id)) {
            $companyId = $notifiable->company_id;
        }

        if ($companyId === null && method_exists($notification, 'companyId')) {
            $companyId = $notification->companyId();
        }

        $payload['company_id'] = $companyId;

        return $payload;
    }
}
