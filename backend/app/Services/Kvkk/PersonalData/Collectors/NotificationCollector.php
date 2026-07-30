<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\User;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'notification';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.notification';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(DatabaseNotification::class)) {
            return [];
        }

        try {
            $records = DatabaseNotification::query()
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $userId)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(['id', 'type', 'data', 'read_at', 'created_at'])
                ->map(fn ($n) => [
                    'source' => 'notifications',
                    'id' => $n->id,
                    'type' => $n->type,
                    'data' => $n->data,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }

        return $records === [] ? [] : [[
            'category' => 'notification',
            'label' => 'Bildirimler',
            'records' => $records,
            'files' => [],
        ]];
    }

    public function destroy(int $subjectId, int $companyId, string $strategy): void
    {
        // D2c
    }
}
