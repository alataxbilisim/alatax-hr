<?php

namespace App\Console\Commands;

use App\Services\Rebrand\BrandDataRebrandService;
use Illuminate\Console\Command;

class RebrandDataCommand extends Command
{
    protected $signature = 'dobedan:rebrand-data {--dry-run : Yalnız hangi tabloda kaç satırın değişeceğini listeler}';

    protected $description = 'Veritabanındaki eski ürün adını Dobedan adlandırmasına çevirir (tek transaction, idempotent; activity_logs ve telescope_* hariç)';

    public function handle(BrandDataRebrandService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $service->run($dryRun);

        $this->info($dryRun ? 'DRY-RUN — hiçbir satır değiştirilmedi.' : 'Dönüşüm uygulandı.');

        if ($result['changes'] === []) {
            $this->line('Değişecek satır yok.');
        } else {
            $this->table(['Tablo', 'Kolon', 'Satır'], array_map(
                fn ($c) => [$c['table'], $c['column'], $c['rows']],
                $result['changes'],
            ));
        }

        if ($result['skipped'] !== []) {
            $this->warn('Atlanan satırlar:');
            $this->table(['Tablo', 'Kolon', 'Anahtar', 'Neden'], array_map(
                fn ($s) => [$s['table'], $s['column'], $s['key'], $s['reason']],
                $result['skipped'],
            ));
        }

        $total = array_sum(array_column($result['changes'], 'rows'));
        $this->line("Toplam: {$total} satır".($dryRun ? ' (değişecek)' : ' (değişti)').', '.count($result['skipped']).' atlandı.');

        return self::SUCCESS;
    }
}
