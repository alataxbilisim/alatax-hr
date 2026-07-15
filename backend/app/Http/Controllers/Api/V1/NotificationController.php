<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * Bildirim listesi (Laravel DatabaseNotification → FE şekli).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $user->notifications()->orderBy('created_at', 'desc');

        if ($user->company_id !== null) {
            $query->where(function ($q) use ($user): void {
                $q->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            });
        }

        $notifications = $query->paginate($request->get('per_page', 20));
        $unreadCount = $user->unreadNotifications()
            ->when($user->company_id !== null, function ($q) use ($user): void {
                $q->where(function ($inner) use ($user): void {
                    $inner->where('company_id', $user->company_id)
                        ->orWhereNull('company_id');
                });
            })
            ->count();

        $items = collect($notifications->items())->map(fn ($n) => $this->serialize($n))->all();

        return $this->success([
            'notifications' => $items,
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Bildirimi okundu olarak işaretle
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if (! $notification) {
            return $this->notFound('Bildirim bulunamadı');
        }

        if ($request->user()->company_id !== null
            && $notification->company_id !== null
            && (int) $notification->company_id !== (int) $request->user()->company_id) {
            return $this->notFound('Bildirim bulunamadı');
        }

        $notification->markAsRead();

        return $this->success(null, 'Bildirim okundu olarak işaretlendi');
    }

    /**
     * Tüm bildirimleri okundu olarak işaretle
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(null, 'Tüm bildirimler okundu olarak işaretlendi');
    }

    /**
     * @param  \Illuminate\Notifications\DatabaseNotification  $n
     * @return array<string, mixed>
     */
    private function serialize(object $n): array
    {
        $data = is_array($n->data) ? $n->data : [];

        return [
            'id' => $n->id,
            'type' => $data['event'] ?? $n->type,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'link' => $data['link'] ?? null,
            'panel' => $data['panel'] ?? null,
            'data' => $data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
            'company_id' => $n->company_id,
        ];
    }
}
