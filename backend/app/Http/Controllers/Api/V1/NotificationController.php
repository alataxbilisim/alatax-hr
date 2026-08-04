<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * Bildirim listesi — aktif şirket + null company_id.
     * other_company_unread: diğer membership şirketlerindeki okunmamış (rozete).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeCompanyId = $this->getCompanyId();

        $query = $user->notifications()->orderBy('created_at', 'desc');

        if ($activeCompanyId !== null) {
            $query->where(function ($q) use ($activeCompanyId): void {
                $q->where('company_id', $activeCompanyId)
                    ->orWhereNull('company_id');
            });
        }

        $notifications = $query->paginate($request->get('per_page', 20));
        $unreadCount = $user->unreadNotifications()
            ->when($activeCompanyId !== null, function ($q) use ($activeCompanyId): void {
                $q->where(function ($inner) use ($activeCompanyId): void {
                    $inner->where('company_id', $activeCompanyId)
                        ->orWhereNull('company_id');
                });
            })
            ->count();

        $otherCompanyUnread = 0;
        if ($activeCompanyId !== null) {
            $accessible = app(\App\Services\CompanyContextService::class)->accessibleCompanyIds($user);
            $otherIds = array_values(array_filter($accessible, fn (int $id) => $id !== $activeCompanyId));
            if ($otherIds !== []) {
                $otherCompanyUnread = $user->unreadNotifications()
                    ->whereIn('company_id', $otherIds)
                    ->count();
            }
        }

        $items = collect($notifications->items())->map(fn ($n) => $this->serialize($n))->all();

        return $this->success([
            'notifications' => $items,
            'unread_count' => $unreadCount,
            'other_company_unread' => $otherCompanyUnread,
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

        $activeCompanyId = $this->getCompanyId();
        if ($activeCompanyId !== null
            && $notification->company_id !== null
            && (int) $notification->company_id !== (int) $activeCompanyId) {
            return $this->notFound('Bildirim bulunamadı');
        }

        $notification->markAsRead();

        return $this->success(null, 'Bildirim okundu olarak işaretlendi');
    }

    /**
     * Tüm bildirimleri okundu olarak işaretle (aktif şirket + null)
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeCompanyId = $this->getCompanyId();

        $query = $user->unreadNotifications();
        if ($activeCompanyId !== null) {
            $query->where(function ($q) use ($activeCompanyId): void {
                $q->where('company_id', $activeCompanyId)
                    ->orWhereNull('company_id');
            });
        }
        $query->get()->markAsRead();

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
