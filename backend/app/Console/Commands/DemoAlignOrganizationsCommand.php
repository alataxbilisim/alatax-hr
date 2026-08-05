<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoOrganizationAligner;
use Illuminate\Console\Command;

/**
 * Mevcut DB'de demo-firma / otel-b / otel-c → tek holding organization.
 * migrate:fresh yok; orphan organization satırlarını SİLMEZ (raporlar).
 */
class DemoAlignOrganizationsCommand extends Command
{
    protected $signature = 'demo:align-organizations
                            {--with-memberships : admin@demo.test / ik@demo.test membership yenile}';

    protected $description = 'Demo holding: üç demo şirketini tek organization altında hizala (idempotent, silmez)';

    public function handle(DemoOrganizationAligner $aligner): int
    {
        if (app()->environment('production')) {
            $this->error('production ortamında çalıştırılamaz.');

            return self::FAILURE;
        }

        $result = $aligner->align();
        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->table(
            ['id', 'slug', 'organization_id'],
            array_map(
                fn (array $c) => [$c['id'], $c['slug'], $c['organization_id']],
                $result['companies']
            )
        );

        if ($result['orphan_org_ids'] !== []) {
            $this->warn(
                'Boş kalan eski organization id (otomatik silinmedi): '
                .implode(', ', $result['orphan_org_ids'])
                .' — isteğe bağlı: DELETE FROM organizations WHERE id IN (...);'
            );
        }

        if ($this->option('with-memberships')) {
            $emails = $aligner->ensureCenterMemberships();
            $this->info('Membership yenilendi: '.($emails === [] ? '(kullanıcı yok)' : implode(', ', $emails)));
        }

        return self::SUCCESS;
    }
}
