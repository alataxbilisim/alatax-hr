<?php

namespace Tests\Architecture;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tests\TestCase;

/**
 * Laravel `exists:` kuralı global scope uygulamaz; tenant tablosuna şirket kapsamı olmadan
 * yazılırsa başka şirketin kaydı validasyondan geçer (Adım 03B S2).
 *
 * Tenant tablolarında: `company_exists:tablo` (veya `Rule::exists(...)->where('company_id', …)`),
 * kullanıcılar için: `company_member` / `company_member:active`.
 */
class TenantExistsRuleGuardTest extends TestCase
{
    private const RULE_EXISTS_WINDOW = 300;

    public function test_tenant_tables_have_no_unscoped_exists_rules(): void
    {
        $tenantTables = $this->tenantTables();
        $this->assertContains('employees', $tenantTables);

        $violations = [];

        foreach ($this->phpFiles() as $relative => $content) {
            if (preg_match_all('/(?<![a-z_])exists:([a-z_]+)/', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    if (in_array($match[1][0], $tenantTables, true)) {
                        $violations[] = $relative.':'.$this->line($content, $match[0][1]).' '.$match[0][0];
                    }
                }
            }

            if (preg_match_all('/Rule::exists\(\s*[\'"]([a-z_]+)[\'"]/', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $table = $match[1][0];
                    if (! in_array($table, $tenantTables, true)) {
                        continue;
                    }

                    $window = substr($content, $match[0][1], self::RULE_EXISTS_WINDOW);
                    $scoped = $table === 'users'
                        ? str_contains($window, 'company_user') || str_contains($window, 'home_company_id')
                        : str_contains($window, 'company_id');

                    if (! $scoped) {
                        $violations[] = $relative.':'.$this->line($content, $match[0][1]).' Rule::exists(\''.$table.'\') şirket kapsamı yok';
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Tenant tablosuna kapsamsız exists kuralı:\n".implode("\n", $violations));
    }

    /**
     * BelongsToCompany kullanan modellerin tabloları + users (üyelik company_user ile).
     *
     * @return list<string>
     */
    private function tenantTables(): array
    {
        $tables = ['users'];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Models'), RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '.php'], ['\\', ''], substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', app_path('Models'))) + 1));
            $class = 'App\\Models\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (! $reflection->isInstantiable() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            if (in_array(BelongsToCompany::class, class_uses_recursive($class), true)) {
                /** @var Model $model */
                $model = $reflection->newInstanceWithoutConstructor();
                $tables[] = $model->getTable();
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return array<string, string> app/ göreli yol => içerik
     */
    private function phpFiles(): array
    {
        $files = [];
        $root = app_path();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    private function line(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
}
