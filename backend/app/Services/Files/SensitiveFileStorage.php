<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kişisel / hassas veri içeren dosyaların tek giriş noktası.
 *
 * Yazma her zaman `private` diske yapılır; dosya asla public URL ile sunulmaz,
 * indirme yetki kontrollü controller üzerinden bu sınıfın download() metoduyla olur.
 * Okuma ve silme geriye dönüktür: Adım 03B öncesi `public` diske yazılmış ve henüz
 * `storage:move-sensitive-to-private` ile taşınmamış dosyalar da bulunur.
 */
class SensitiveFileStorage
{
    public const DISK = 'private';

    public const LEGACY_DISK = 'public';

    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::DISK);

        if ($path === false) {
            throw new \RuntimeException('Dosya kaydedilemedi.');
        }

        return $path;
    }

    /** Dosyanın bulunduğu disk; yoksa null. */
    public function locate(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public function exists(?string $path): bool
    {
        return $this->locate($path) !== null;
    }

    /**
     * Çağıran yetkiyi önceden doğrulamış olmalıdır. Dosya yoksa 404.
     *
     * @param  array<string, string>  $headers
     */
    public function download(?string $path, ?string $name = null, array $headers = []): StreamedResponse
    {
        $disk = $this->locate($path);

        abort_if($disk === null, 404);

        return Storage::disk($disk)->download((string) $path, $name, $headers);
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}
