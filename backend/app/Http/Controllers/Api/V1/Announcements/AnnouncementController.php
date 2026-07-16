<?php

namespace App\Http\Controllers\Api\V1\Announcements;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company duyuru CRUD + yayınla (C5).
 */
class AnnouncementController extends BaseController
{
    public function __construct(
        protected AnnouncementPublishService $publisher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Announcement::query()
            ->where('company_id', $this->getCompanyId())
            ->with('author:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', $search)
                    ->orWhere('summary', 'ilike', $search);
            });
        }

        return $this->paginated(
            $query->paginate($request->integer('per_page', 20)),
            'Duyurular listelendi'
        );
    }

    public function show(int $id): JsonResponse
    {
        $row = Announcement::query()
            ->where('company_id', $this->getCompanyId())
            ->with('author:id,name')
            ->findOrFail($id);

        return $this->success($row);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $validated['company_id'] = $this->getCompanyId();
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        $validated['is_published'] = false;

        $row = Announcement::query()->create($validated);
        ActivityLog::log('create', $row, 'Duyuru oluşturuldu: '.$row->title);

        return $this->success($row, 'Duyuru oluşturuldu', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = Announcement::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $validated = $this->validatePayload($request, false);
        $validated['updated_by'] = auth()->id();
        // Yayın durumu publish aksiyonu ile
        unset($validated['is_published'], $validated['published_at']);

        $row->update($validated);
        ActivityLog::log('update', $row, 'Duyuru güncellendi: '.$row->title);

        return $this->success($row->fresh(), 'Duyuru güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        $row = Announcement::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        ActivityLog::log('delete', $row, 'Duyuru silindi: '.$row->title);
        $row->delete();

        return $this->success(null, 'Duyuru silindi');
    }

    public function publish(int $id): JsonResponse
    {
        $row = Announcement::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $result = $this->publisher->publish($row, auth()->id());
        ActivityLog::log('publish', $row, 'Duyuru yayınlandı: '.$row->title);

        return $this->success([
            'announcement' => $row->fresh(),
            'notified' => $result['notified'],
        ], 'Duyuru yayınlandı');
    }

    public function unpublish(int $id): JsonResponse
    {
        $row = Announcement::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $row->update([
            'is_published' => false,
            'updated_by' => auth()->id(),
        ]);
        ActivityLog::log('unpublish', $row, 'Duyuru yayından alındı: '.$row->title);

        return $this->success($row->fresh(), 'Duyuru taslağa alındı');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'content' => ($creating ? 'required' : 'sometimes').'|string|max:20000',
            'summary' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:general,urgent,important,info',
            'category' => 'nullable|string|max:100',
            'is_for_all' => 'sometimes|boolean',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'integer',
            'target_branches' => 'nullable|array',
            'target_branches.*' => 'integer',
            'target_positions' => 'nullable|array',
            'target_employees' => 'nullable|array',
            'target_employees.*' => 'integer',
            'expires_at' => 'nullable|date',
            'is_pinned' => 'sometimes|boolean',
            'pin_order' => 'nullable|integer|min:0',
            'requires_acknowledgment' => 'sometimes|boolean',
        ]);
    }
}
