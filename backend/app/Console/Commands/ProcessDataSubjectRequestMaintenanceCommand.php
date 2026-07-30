<?php

namespace App\Console\Commands;

use App\Enums\DataSubjectRequestStatus;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Services\Kvkk\PersonalDataExportService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/**
 * D2b — süresi dolmuş ihraç paketlerini sil + 25. gün uyarıları.
 */
class ProcessDataSubjectRequestMaintenanceCommand extends Command
{
    protected $signature = 'kvkk:data-subject-maintenance';

    protected $description = 'D2b: ihraç paketlerini purge et + süresi yaklaşan talepleri bildir';

    public function handle(PersonalDataExportService $exports, NotificationService $notifications): int
    {
        $purged = $exports->purgeExpired();
        $this->info("Purged packages: {$purged}");

        $targets = DataSubjectRequest::query()
            ->whereNotIn('status', [
                DataSubjectRequestStatus::Completed->value,
                DataSubjectRequestStatus::Rejected->value,
            ])
            ->whereDate('due_date', '=', now()->addDays(5)->toDateString())
            ->get();

        foreach ($targets as $req) {
            $userId = $req->assigned_to;
            if (! $userId) {
                continue;
            }
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }
            $notifications->notify($user, 'kvkk.data_subject.due_soon', [
                'company_id' => $req->company_id,
                'title' => 'KVKK talebi süresi yaklaşıyor',
                'entity' => '#'.$req->id,
                'path' => '/kvkk?tab=requests&id='.$req->id,
                'panel' => 'company',
            ]);
        }

        $this->info('Due-soon notifications: '.$targets->count());

        return self::SUCCESS;
    }
}
