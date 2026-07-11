<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP 2FA — pragmarx/google2fa + bacon/bacon-qr-code (SVG).
 */
class TwoFactorService
{
    public const CHALLENGE_ABILITY = '2fa-challenge';

    public const CHALLENGE_TOKEN_NAME = '2fa-challenge';

    public const CHALLENGE_TTL_MINUTES = 5;

    public const WINDOW = 1;

    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        protected Google2FA $google2fa = new Google2FA,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * @return list<string> düz metin recovery kodları (yalnızca bir kez gösterilir)
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $plainCodes
     * @return list<string> bcrypt hash'ler
     */
    public function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)), $plainCodes);
    }

    public function encryptSecret(string $secret): string
    {
        return encrypt($secret);
    }

    public function decryptSecret(string $encrypted): string
    {
        return decrypt($encrypted);
    }

    public function getOtpAuthUrl(User $user, string $secret): string
    {
        $issuer = $user->company?->name ?? config('app.name', 'ALATAX HR');

        return $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);
    }

    public function generateQrSvg(string $otpAuthUrl, int $size = 200): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size),
                new SvgImageBackEnd
            )
        );

        return $writer->writeString($otpAuthUrl);
    }

    public function verifyTotp(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code, self::WINDOW);
    }

    /**
     * @param  list<string>|null  $hashedCodes
     * @return array{0: bool, 1: list<string>|null} [eşleşti mi, kalan hash listesi]
     */
    public function consumeRecoveryCode(?array $hashedCodes, string $plainCode): array
    {
        if ($hashedCodes === null || $hashedCodes === []) {
            return [false, $hashedCodes];
        }

        $normalized = $this->normalizeRecoveryCode($plainCode);
        $remaining = [];
        $matched = false;

        foreach ($hashedCodes as $hash) {
            if (! $matched && Hash::check($normalized, $hash)) {
                $matched = true;

                continue;
            }
            $remaining[] = $hash;
        }

        return [$matched, $matched ? array_values($remaining) : $hashedCodes];
    }

    public function storeEncryptedRecoveryHashes(array $hashes): string
    {
        return encrypt(json_encode(array_values($hashes)));
    }

    /**
     * @return list<string>|null
     */
    public function decryptRecoveryHashes(?string $encrypted): ?array
    {
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        try {
            $decoded = json_decode(decrypt($encrypted), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? array_values($decoded) : null;
    }

    protected function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(str_replace([' ', '-'], '', $code));
    }
}
