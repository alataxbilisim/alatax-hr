<?php

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Koddaki her sabit disk adı `config('filesystems.disks')` içinde tanımlı olmalı;
 * tanımsız disk çalışma anında 500 üretir (K2: `private` diski).
 */
class StorageDiskNameGuardTest extends TestCase
{
    private const SCANNED_DIRECTORIES = ['app', 'routes', 'database'];

    /** `Storage::disk('x')` ve `->store(…, 'x')` / `->storeAs(…, 'x')` biçimleri. */
    private const PATTERNS = [
        '/Storage::disk\(\s*[\'"]([A-Za-z0-9_-]+)[\'"]\s*\)/',
        '/->store(?:As|Publicly|PubliclyAs)?\((?:[^()]|\([^()]*\))*?,\s*[\'"]([A-Za-z0-9_-]+)[\'"]\s*\)/s',
    ];

    public function test_every_literal_disk_name_is_configured(): void
    {
        $configured = array_keys(config('filesystems.disks'));
        $violations = [];
        $found = 0;

        foreach ($this->phpFiles() as $file) {
            $content = (string) file_get_contents($file->getPathname());

            foreach (self::PATTERNS as $pattern) {
                if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[1] as [$disk, $offset]) {
                    $found++;
                    if (! in_array($disk, $configured, true)) {
                        $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                        $violations[] = sprintf('%s:%d disk=%s', $this->relative($file), $line, $disk);
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $found, 'Desen hiçbir disk çağrısı bulmadı; test bozulmuş olabilir.');
        $this->assertSame([], $violations, "Tanımsız disk adları:\n".implode("\n", $violations));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(): iterable
    {
        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    yield $file;
                }
            }
        }
    }

    private function relative(SplFileInfo $file): string
    {
        return str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
    }
}
