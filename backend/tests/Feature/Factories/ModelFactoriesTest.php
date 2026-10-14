<?php

namespace Tests\Feature\Factories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Architecture\ModelFactoryCoverageTest;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Her factory tek başına create() edilebilir ve tenant-doğrudur: kaydın doğrudan
 * şirketli ebeveynleri (users için home_company_id) kaydın kendi şirketine aittir.
 */
class ModelFactoriesTest extends TestCase
{
    use RefreshDatabase;

    /** Ebeveyn şirket uyumu aranmayan modeller (sınıf adı => gerekçe). */
    private const TENANT_CHECK_EXEMPT = [
        'CompanyUser' => 'Üyelik pivotu: kullanıcının ev şirketi dışındaki şirket üyeliklerini tutmak için var.',
    ];

    /** @return array<string, array{class-string<Model>}> */
    public static function factoryModels(): array
    {
        $cases = [];
        foreach (ModelFactoryCoverageTest::modelClasses() as $class) {
            if (! array_key_exists(class_basename($class), ModelFactoryCoverageTest::EXCEPTIONS)) {
                $cases[class_basename($class)] = [$class];
            }
        }

        return $cases;
    }

    /** @param  class-string<Model>  $class */
    #[DataProvider('factoryModels')]
    public function test_factory_creates_a_tenant_consistent_record(string $class): void
    {
        $model = $class::factory()->create();
        $table = $model->getTable();

        $this->assertTrue(
            DB::table($table)->where($model->getKeyName(), $model->getKey())->exists(),
            "{$class} factory kaydı veritabanına yazılmadı"
        );

        $own = Schema::hasColumn($table, 'company_id') ? $model->getAttribute('company_id') : null;
        if (Schema::hasColumn($table, 'company_id')) {
            $this->assertNotNull($own, "{$class} factory company_id üretmedi");
        }
        if (array_key_exists(class_basename($class), self::TENANT_CHECK_EXEMPT)) {
            return;
        }

        $parentCompanies = [];
        foreach (Schema::getForeignKeys($table) as $fk) {
            if (count($fk['columns']) !== 1) {
                continue;
            }
            $column = $fk['columns'][0];
            $value = $model->getAttribute($column);
            if ($value === null || $column === 'company_id') {
                continue;
            }
            $companyColumn = match (true) {
                $fk['foreign_table'] === 'users' => 'home_company_id',
                Schema::hasColumn($fk['foreign_table'], 'company_id') => 'company_id',
                default => null,
            };
            if ($companyColumn === null) {
                continue;
            }
            $parentCompany = DB::table($fk['foreign_table'])->where($fk['foreign_columns'][0], $value)->value($companyColumn);
            $parentCompanies[$column] = $parentCompany === null ? null : (int) $parentCompany;
        }

        if ($own !== null) {
            foreach ($parentCompanies as $column => $company) {
                $this->assertSame((int) $own, $company, "{$class}.{$column} başka şirkete ait ebeveyne bağlandı");
            }
        } else {
            $this->assertLessThanOrEqual(
                1,
                count(array_unique(array_filter($parentCompanies, fn ($c) => $c !== null))),
                "{$class} ebeveynleri farklı şirketlere ait: ".json_encode($parentCompanies)
            );
        }
    }
}
