<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Announcement;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalAnnouncementController extends BaseController
{
    /**
     * Duyuruları listele
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $query = Announcement::where('company_id', $user->company_id)
            ->active()
            ->orderByPinned();

        // Tip filtresi
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Kategori filtresi
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $announcements = $query->paginate($request->get('per_page', 15));

        // Her duyuru için okunma durumunu ekle
        $announcements->getCollection()->transform(function ($announcement) use ($user) {
            return [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'summary' => $announcement->summary,
                'type' => $announcement->type,
                'type_label' => $announcement->type_label,
                'category' => $announcement->category,
                'image_path' => $announcement->image_path,
                'is_pinned' => $announcement->is_pinned,
                'published_at' => $announcement->published_at,
                'is_read' => $announcement->isReadBy($user),
                'requires_acknowledgment' => $announcement->requires_acknowledgment,
            ];
        });

        return $this->paginated($announcements);
    }

    /**
     * Duyuru detayı
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $announcement = Announcement::where('company_id', $user->company_id)
            ->where('id', $id)
            ->active()
            ->first();

        if (!$announcement) {
            return $this->error('Duyuru bulunamadı', null, 404);
        }

        // Personel bu duyuruyu görebilir mi?
        if (!$announcement->canBeViewedBy($employee)) {
            return $this->error('Bu duyuruya erişim izniniz yok', null, 403);
        }

        // Okundu olarak işaretle
        $announcement->markAsReadBy($user);

        // Onay durumunu kontrol et
        $read = $announcement->reads()->where('user_id', $user->id)->first();

        return $this->success([
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'summary' => $announcement->summary,
            'type' => $announcement->type,
            'type_label' => $announcement->type_label,
            'category' => $announcement->category,
            'image_path' => $announcement->image_path,
            'attachments' => $announcement->attachments,
            'is_pinned' => $announcement->is_pinned,
            'published_at' => $announcement->published_at,
            'expires_at' => $announcement->expires_at,
            'requires_acknowledgment' => $announcement->requires_acknowledgment,
            'is_acknowledged' => $read?->acknowledged ?? false,
            'acknowledged_at' => $read?->acknowledged_at,
            'author' => $announcement->author?->name,
        ]);
    }

    /**
     * Duyuruyu onayla
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $announcement = Announcement::where('company_id', $user->company_id)
            ->where('id', $id)
            ->active()
            ->first();

        if (!$announcement) {
            return $this->error('Duyuru bulunamadı', null, 404);
        }

        if (!$announcement->requires_acknowledgment) {
            return $this->error('Bu duyuru onay gerektirmiyor', null, 422);
        }

        $read = $announcement->reads()
            ->where('user_id', $user->id)
            ->first();

        if (!$read) {
            // Henüz okunmamışsa önce oku
            $announcement->markAsReadBy($user);
            $read = $announcement->reads()->where('user_id', $user->id)->first();
        }

        if ($read->acknowledged) {
            return $this->error('Bu duyuru zaten onaylanmış', null, 422);
        }

        $read->acknowledge();

        return $this->success(null, 'Duyuru onaylandı');
    }

    /**
     * Okunmamış duyuru sayısı
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return $this->success(['count' => 0]);
        }

        $readAnnouncementIds = \DB::table('announcement_reads')
            ->where('user_id', $user->id)
            ->pluck('announcement_id')
            ->toArray();

        $count = Announcement::where('company_id', $user->company_id)
            ->active()
            ->whereNotIn('id', $readAnnouncementIds)
            ->count();

        return $this->success(['count' => $count]);
    }
}

