<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Statik kod kuralları: config cache'i bozan env(), controller'da ham DDL/SQL,
 * unutulmuş debug çağrıları.
 */
class CodeRulesTest extends TestCase
{
    public function test_app_code_does_not_call_env_directly(): void
    {
        $this->assertNoMatches(['app'], '/(?<![\w>:$\\\\])env\s*\(/', 'env() yalnız config/ içinde kullanılır; app/ config() okur');
    }

    public function test_controllers_do_not_run_raw_statements(): void
    {
        $this->assertNoMatches(
            ['app/Http/Controllers'],
            '/DB::(statement|unprepared)\s*\(/',
            'Controller\'da DB::statement/DB::unprepared yasak; servis katmanına taşıyın'
        );
    }

    public function test_no_debug_helpers_left_in_code(): void
    {
        $this->assertNoMatches(
            ['app', 'routes', 'database'],
            '/(?<![\w>:$\\\\])(dd|dump|var_dump)\s*\(/',
            'dd()/dump()/var_dump() kodda bırakılmaz'
        );
    }

    /** @param  list<string>  $directories */
    private function assertNoMatches(array $directories, string $pattern, string $message): void
    {
        $root = dirname(__DIR__, 2);
        $hits = [];

        foreach ($directories as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                foreach (file($file->getPathname()) as $number => $line) {
                    $trimmed = ltrim($line);
                    if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#')) {
                        continue;
                    }
                    if (preg_match($pattern, $line)) {
                        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                        $hits[] = $relative.':'.($number + 1).': '.trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $hits, $message.":\n".implode("\n", $hits));
    }
}
