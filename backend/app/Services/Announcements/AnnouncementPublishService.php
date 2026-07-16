<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;

/**
 * Duyuru yayın + hedef kitle bildirimi (C5).
 */
class AnnouncementPublishService
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * @return array{notified: int}
     */
    public function publish(Announcement $announcement, ?int $actorId = null): array
    {
        if (! $announcement->is_published) {
            $announcement->update([
                'is_published' => true,
                'published_at' => $announcement->published_at ?? now(),
                'updated_by' => $actorId,
            ]);
        }

        return ['notified' => $this->notifyAudience($announcement->fresh() ?? $announcement)];
    }

    public function notifyAudience(Announcement $announcement): int
    {
        if (! $announcement->is_published) {
            return 0;
        }

        $employees = Employee::query()
            ->where('company_id', $announcement->company_id)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->with('user:id,name,email,company_id,preferences')
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            if (! $announcement->canBeViewedBy($employee)) {
                continue;
            }
            $user = $employee->user;
            if (! $user instanceof User) {
                continue;
            }
            if ((int) $user->company_id !== (int) $announcement->company_id) {
                continue;
            }

            $this->notifications->notify($user, 'announcement.published', [
                'company_id' => (int) $announcement->company_id,
                'entity' => $announcement->title,
                'title' => $announcement->title,
                'user' => $user->name,
                'date' => now()->toDateString(),
                'panel' => 'portal',
                'path' => '/announcements',
                'link_params' => ['id' => $announcement->id],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, Announcement>
     */
    public function visibleForEmployee(Employee $employee): Collection
    {
        return Announcement::query()
            ->where('company_id', $employee->company_id)
            ->active()
            ->orderByPinned()
            ->get()
            ->filter(fn (Announcement $a) => $a->canBeViewedBy($employee))
            ->values();
    }
}
