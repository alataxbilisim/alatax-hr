<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * FAZ A6 uyumluluk sarmalayıcısı — tek kaynak: DemoDataSeeder (QA-4).
 *
 * php artisan db:seed --class=DemoSeeder
 * Tercih edilen: php artisan demo:seed
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = DemoDataSeeder::PASSWORD;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder production ortamında çalıştırılamaz.');
            Log::warning('DemoSeeder blocked in production');

            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
