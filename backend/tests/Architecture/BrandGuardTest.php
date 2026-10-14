<?php

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Ürün adı Dobedan HR: eski ad backend kaynaklarına geri gelmesin.
 *
 * `brand-guard:allow-start` / `brand-guard:allow-end` arasındaki satırlar (tek seferlik
 * dönüşüm eşlemeleri ve test fikstürleri) taranmaz; aynı kural scripts/check-brand.mjs'te geçerlidir.
 */
class BrandGuardTest extends TestCase
{
    private const SCANNED_DIRECTORIES = ['app', 'config', 'database', 'routes', 'resources', 'tests'];

    /** Büyük/küçük harf duyarsız aranır. */
    private const OLD_BRAND = 'alatax';

    private const ALLOW_START = 'brand-guard:allow-start';

    private const ALLOW_END = 'brand-guard:allow-end';

    public function test_backend_sources_do_not_contain_old_brand(): void
    {
        $violations = [];

        foreach ($this->files() as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

            if (stripos($relative, self::OLD_BRAND) !== false) {
                $violations[] = "{$relative} [dosya adı]";
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false || str_contains($content, "\0")) {
                continue;
            }

            $allowed = false;
            foreach (preg_split('/\R/', $content) as $index => $line) {
                if (str_contains($line, self::ALLOW_START)) {
                    $allowed = true;

                    continue;
                }
                if (str_contains($line, self::ALLOW_END)) {
                    $allowed = false;

                    continue;
                }
                if (! $allowed && stripos($line, self::OLD_BRAND) !== false) {
                    $violations[] = sprintf('%s:%d %s', $relative, $index + 1, trim($line));
                }
            }

            if ($allowed) {
                $violations[] = "{$relative} [kapanmamış brand-guard:allow-start]";
            }
        }

        $this->assertSame([], $violations, "Eski ürün adı bulundu:\n".implode("\n", $violations));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function files(): iterable
    {
        $self = realpath(__FILE__);

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getRealPath() !== $self) {
                    yield $file;
                }
            }
        }
    }
}
