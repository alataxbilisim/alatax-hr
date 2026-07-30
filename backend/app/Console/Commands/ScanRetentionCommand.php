<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\DestructionLog;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Kvkk\Retention\DestructionEngine;
use App\Services\Notification\NotificationService;
use App\Services\Settings\Settings;
use Illuminate\Console\Command;

/**
 * D2c — Periyodik tarama (yalnız ADAY LİSTESİ) + imha hatırlatması.
 * Hiçbir veri otomatik imha edilmez.
 */
class ScanRetentionCommand extends Command
{
    protected $signature = 'kvkk:scan-retention {--company= : Tek firma}';

    protected $description = 'D2c: aktif politikalara göre imha aday listesi üretir (veriye dokunmaz)';

    public function handle(DestructionEngine $engine, NotificationService $notifications): int
    {
        $q = Company::query();
        if ($this->option('company')) {
            $q->where('id', (int) $this->option('company'));
        }

        $totalCreated = 0;
        $totalHold = 0;

        foreach ($q->cursor() as $company) {
            $activeCount = RetentionPolicy::query()
                ->where('company_id', $company->id)
                ->where('active', true)
                ->count();
            if ($activeCount === 0) {
                continue;
            }

            $result = $engine->scan((int) $company->id);
            $totalCreated += $result['created'];
            $totalHold += $result['skipped_hold'];
            $this->info("Company {$company->id}: created={$result['created']} hold_skip={$result['skipped_hold']}");

            $this->maybeRemindReview((int) $company->id, $notifications);
        }

        $this->info("Toplam aday: {$totalCreated}, hold atlanan: {$totalHold}");

        return self::SUCCESS;
    }

    private function maybeRemindReview(int $companyId, NotificationService $notifications): void
    {
        $months = (int) Settings::get('kvkk.destruction.review_period_months', ['company_id' => $companyId]);
        if ($months < 1) {
            $months = 6;
        }

        $last = DestructionLog::query()
            ->where('company_id', $companyId)
            ->where('dry_run', false)
            ->where('outcome', 'success')
            ->orderByDesc('id')
            ->value('created_at');

        $due = ! $last || \Carbon\Carbon::parse($last)->lte(now()->subMonths($months));
        if (! $due) {
            return;
        }

        $admins = User::query()
            ->where('company_id', $companyId)
            ->where('type', \App\Enums\UserType::CompanyAdmin)
            ->limit(5)
            ->get();

        foreach ($admins as $admin) {
            try {
                $notifications->notify($admin, 'kvkk.destruction.review_due', [
                    'company_id' => $companyId,
                    'entity' => 'imha-inceleme',
                    'date' => now()->toDateString(),
                    'path' => '/kvkk?tab=destruction',
                    'panel' => 'company',
                ]);
            } catch (\Throwable) {
                // katalog yoksa sessiz
            }
        }
    }
}
