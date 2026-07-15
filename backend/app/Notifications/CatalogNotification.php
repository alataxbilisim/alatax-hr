<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Katalog olayı → in-app (database) kaydı.
 * E-posta ayrı queued Mailable ile gider (senkron mail yok).
 */
class CatalogNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $eventKey,
        public array $data,
        public ?int $companyId = null,
    ) {}

    public function companyId(): ?int
    {
        return $this->companyId;
    }

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
        return array_merge($this->data, [
            'event' => $this->eventKey,
        ]);
    }
}
