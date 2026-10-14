<?php

namespace Tests\Architecture;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * company_id kolonu taşıyan her tablonun modeli BelongsToCompany kullanmalı; aksi halde
 * global tenant scope'u devreye girmez. İstisnalar gerekçesiyle aşağıda.
 */
class TenantModelGuardTest extends TestCase
{
    use RefreshDatabase;

    /** company_id'li ama BelongsToCompany kullanmayan modeller (tablo => gerekçe). */
    private const MODEL_EXCEPTIONS = [
        'company_ledger' => 'Süper admin cari hesabı; şirketler arası görünüm tasarım gereği (super_admin route\'ları).',
        'company_user' => 'Kullanıcı-şirket üyelik pivotu; bağlam çözümlemesinin kendisi.',
        'holidays' => 'company_id nullable: sistem geneli tatiller + şirket tatilleri (scopeForCompany).',
        'lookups' => 'company_id nullable: sistem satırları + şirket override birleşimi.',
        'setting_values' => 'company_id nullable: system seviyesindeki ayarlar.',
    ];

    /** company_id'li ama Eloquent modeli olmayan tablolar (uygulama kodu erişmiyor). */
    private const TABLES_WITHOUT_MODEL = [
        'application_sources',
        'asset_requests',
        'buddy_assignments',
        'buddy_pool',
        'company_modules',
        'enps_records',
        'job_offers',
        'mandatory_trainings',
        'notifications',
        'onboarding_milestones',
        'onboarding_surveys',
        'preboarding_tokens',
        'required_documents',
        'training_requests',
        'user_document_status',
        'user_learning_paths',
    ];

    public function test_every_company_scoped_table_has_a_belongs_to_company_model(): void
    {
        $models = $this->modelsByTable();
        $problems = [];

        foreach ($this->tablesWithCompanyId() as $table) {
            if (array_key_exists($table, self::MODEL_EXCEPTIONS)) {
                continue;
            }

            $classes = $models[$table] ?? [];
            if ($classes === []) {
                if (! in_array($table, self::TABLES_WITHOUT_MODEL, true)) {
                    $problems[] = "{$table}: model yok (TABLES_WITHOUT_MODEL'a gerekçeyle ekleyin ya da model yazın)";
                }

                continue;
            }

            foreach ($classes as $class) {
                if (! in_array(BelongsToCompany::class, class_uses_recursive($class), true)) {
                    $problems[] = "{$table}: {$class} BelongsToCompany kullanmıyor";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_exception_lists_have_no_stale_entries(): void
    {
        $tables = $this->tablesWithCompanyId();
        $models = $this->modelsByTable();

        foreach (array_keys(self::MODEL_EXCEPTIONS) as $table) {
            $this->assertContains($table, $tables, "İstisnadaki tabloda artık company_id yok: {$table}");
            $this->assertArrayHasKey($table, $models, "İstisnadaki tablonun modeli yok: {$table}");
        }

        foreach (self::TABLES_WITHOUT_MODEL as $table) {
            $this->assertContains($table, $tables, "Listede olan tabloda artık company_id yok: {$table}");
            $this->assertArrayNotHasKey($table, $models, "Tablonun artık modeli var, listeden çıkarın: {$table}");
        }
    }

    /** @return list<string> */
    private function tablesWithCompanyId(): array
    {
        $tables = [];
        foreach (Schema::getTables() as $table) {
            if (($table['schema'] ?? 'public') !== 'public') {
                continue;
            }
            if (Schema::hasColumn($table['name'], 'company_id')) {
                $tables[] = $table['name'];
            }
        }
        sort($tables);

        return $tables;
    }

    /** @return array<string, list<class-string<Model>>> */
    private function modelsByTable(): array
    {
        $base = app_path('Models');
        $map = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($base) + 1, -4);
            $class = 'App\\Models\\'.str_replace(['/', '\\'], '\\', $relative);
            if (! class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }
            $map[(new $class)->getTable()][] = $class;
        }

        return $map;
    }
}
