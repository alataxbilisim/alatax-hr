<?php

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * `public` disk (web'den `/storage/...` ile erişilebilir) yalnız gerçekten herkese açık
 * varlıklar içindir: avatar ve şirket logosu. Kişisel / hassas yüklemeler
 * `App\Services\Files\SensitiveFileStorage` ile `private` diske gider (Adım 03B S1).
 *
 * Yeni bir dosya public disk kullanacaksa önce bunun herkese açık olması gerektiğini
 * doğrula, sonra ALLOWED listesine gerekçesiyle ekle.
 */
class PublicDiskWriteGuardTest extends TestCase
{
    /** dosya (app/ göreli) => gerekçe */
    private const ALLOWED = [
        'Http/Controllers/Api/V1/AuthController.php' => 'Profil avatarı yükleme; avatar ve logo URL\'si',
        'Http/Controllers/Api/V1/UserController.php' => 'Kullanıcı avatarı',
        'Http/Controllers/Api/V1/Portal/PortalProfileController.php' => 'Portal avatarı',
        'Http/Controllers/Api/V1/CompanyController.php' => 'Şirket logosu',
        'Http/Controllers/Api/V1/Public/JobController.php' => 'Kariyer sayfasında şirket logosu URL\'si',
        'Mail/EmployeeInvitation.php' => 'Davet e-postasında şirket logosu URL\'si',
        'Mail/UserInvitation.php' => 'Davet e-postasında şirket logosu URL\'si',
    ];

    /** Public diske yazma, okuma veya public URL üretme biçimleri. */
    private const PATTERNS = [
        '/Storage::disk\(\s*[\'"]public[\'"]\s*\)/',
        '/->store(?:As|Publicly|PubliclyAs)?\((?:[^()]|\([^()]*\))*?,\s*[\'"]public[\'"]\s*\)/s',
        '/storage_path\(\s*[\'"]app\/public/',
        '/asset\(\s*[\'"]storage\//',
        '/Storage::url\(/',
    ];

    public function test_public_disk_is_only_used_by_allowed_files(): void
    {
        $violations = [];

        foreach ($this->usages() as $file => $lines) {
            if (! array_key_exists($file, self::ALLOWED)) {
                $violations[] = $file.':'.implode(',', $lines);
            }
        }

        $this->assertSame([], $violations, "Public disk izinsiz kullanılıyor (hassas dosya ise SensitiveFileStorage kullan):\n".implode("\n", $violations));
    }

    public function test_allow_list_has_no_stale_entries(): void
    {
        $stale = array_values(array_diff(array_keys(self::ALLOWED), array_keys($this->usages())));

        $this->assertSame([], $stale, "Artık public disk kullanmayan dosyaları ALLOWED listesinden çıkar:\n".implode("\n", $stale));
    }

    /**
     * @return array<string, list<int>> dosya => satır numaraları
     */
    private function usages(): array
    {
        $usages = [];
        $root = app_path();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            foreach (self::PATTERNS as $pattern) {
                if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                foreach ($matches[0] as [, $offset]) {
                    $usages[$relative][] = substr_count(substr($content, 0, $offset), "\n") + 1;
                }
            }
        }

        return $usages;
    }
}
