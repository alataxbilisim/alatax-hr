<?php

namespace App\Console\Commands;

use App\Services\Documents\DocumentExpiryAlertService;
use Illuminate\Console\Command;

class ProcessDocumentExpiryAlertsCommand extends Command
{
    protected $signature = 'documents:process-expiry-alerts';

    protected $description = 'Süreli personel evrakları için 30/7 gün kala uyarı gönderir (idempotent)';

    public function handle(DocumentExpiryAlertService $service): int
    {
        $summary = $service->processAllActiveCompanies();

        $this->info(sprintf(
            'Evrak uyarıları: checked=%d notified=%d skipped=%d failed=%d',
            $summary['checked'],
            $summary['notified'],
            $summary['skipped'],
            $summary['failed']
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
