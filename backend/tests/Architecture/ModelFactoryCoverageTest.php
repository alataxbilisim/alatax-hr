<?php

namespace Tests\Architecture;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Her Eloquent modelin bir factory'si ve HasFactory trait'i vardır.
 * Davranış (oluşturulabilirlik + tenant uyumu) Tests\Feature\Factories\ModelFactoriesTest'te.
 */
class ModelFactoryCoverageTest extends TestCase
{
    /** Factory'si bilinçli olarak olmayan modeller (sınıf adı => gerekçe). */
    public const EXCEPTIONS = [];

    /** @return list<class-string<Model>> */
    public static function modelClasses(): array
    {
        $classes = [];
        foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if (! $reflection->isAbstract() && $reflection->isSubclassOf(Model::class)) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }

    public function test_every_model_has_a_factory(): void
    {
        $missing = [];
        foreach (self::modelClasses() as $class) {
            if (array_key_exists(class_basename($class), self::EXCEPTIONS)) {
                continue;
            }
            $factory = 'Database\\Factories\\'.class_basename($class).'Factory';
            if (! class_exists($factory)) {
                $missing[] = "{$class}: {$factory} yok";
            } elseif (! in_array(HasFactory::class, class_uses_recursive($class), true)) {
                $missing[] = "{$class}: HasFactory trait'i yok";
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    public function test_exceptions_are_not_stale(): void
    {
        $names = array_map('class_basename', self::modelClasses());
        $this->assertNotEmpty($names);
        foreach (array_keys(self::EXCEPTIONS) as $name) {
            $this->assertContains($name, $names, "İstisnadaki model artık yok: {$name}");
            $this->assertFalse(
                class_exists('Database\\Factories\\'.$name.'Factory'),
                "İstisnadaki modelin artık factory'si var, listeden çıkarın: {$name}"
            );
        }
    }
}
