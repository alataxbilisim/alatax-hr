<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoSentinel;
use Illuminate\Console\Command;

class DemoSentinelCommand extends Command
{
    protected $signature = 'demo:sentinel';

    protected $description = 'Demo admin sağlık kontrolü (firma + rol + yetki seti)';

    public function handle(DemoSentinel $sentinel): int
    {
        $result = $sentinel->inspect();

        $this->table(
            ['Kontrol', 'Değer'],
            collect($result['checks'])
                ->map(function ($value, $key) {
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }

                    return [$key, $value === true ? 'yes' : ($value === false ? 'no' : (string) $value)];
                })
                ->values()
                ->all()
        );

        if (! $result['ok']) {
            foreach ($result['failures'] as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('Demo sentinel OK');

        return self::SUCCESS;
    }
}
