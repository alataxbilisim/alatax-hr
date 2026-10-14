<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\TwoFactorService;

/**
 * 2FA'lı kullanıcı ve challenge token üretimi (login akışındaki ad/ability ile aynı).
 */
trait CreatesTwoFactorUsers
{
    /**
     * @return string düz metin TOTP sırrı
     */
    protected function enableTwoFactor(User $user): string
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $service->encryptSecret($secret),
            'two_factor_recovery_codes' => $service->storeEncryptedRecoveryHashes(
                $service->hashRecoveryCodes($service->generateRecoveryCodes())
            ),
        ])->save();

        return $secret;
    }

    /**
     * Login'in 2FA aşamasında dönen challenge token'ın aynısı.
     */
    protected function challengeTokenFor(User $user, bool $portal = false): string
    {
        return $user->createToken(
            $portal ? '2fa-challenge-portal' : TwoFactorService::CHALLENGE_TOKEN_NAME,
            [TwoFactorService::CHALLENGE_ABILITY],
            now()->addMinutes(TwoFactorService::CHALLENGE_TTL_MINUTES)
        )->plainTextToken;
    }
}
