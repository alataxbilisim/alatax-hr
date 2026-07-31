<?php

namespace App\Console\Commands;

use App\Models\RetentionPolicy;
use App\Services\Demo\DemoSentinel;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed
                            {--activate-retention : Demo saklama politikasını aktifleştir (varsayılan: pasif; D2c)}';

    protected $description = 'QA/demo veri setini idempotent ekler (production\'da yasak)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('demo:seed production ortamında çalıştırılamaz.');
        }

        $this->call(DemoDataSeeder::class);

        if ($this->option('activate-retention')) {
            $updated = RetentionPolicy::query()
                ->where('name', 'Demo Saklama Politikası')
                ->whereHas('company', fn ($q) => $q->where('slug', DemoSentinel::COMPANY_SLUG))
                ->update(['active' => true]);
            $this->warn("Retention aktifleştirildi (--activate-retention): {$updated} satır");
        } else {
            $this->line('Retention seed pasif (D2c). Dry-run için: demo:seed --activate-retention');
        }

        return $this->call('demo:sentinel') === 0 ? self::SUCCESS : self::FAILURE;
    }
}
