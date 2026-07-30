<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'QA/demo veri setini idempotent ekler (production\'da yasak)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('demo:seed production ortamında çalıştırılamaz.');
        }

        $this->call(DemoDataSeeder::class);

        return self::SUCCESS;
    }
}
