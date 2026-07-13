<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kullanıcı davet token'ı: sha256 saklama, süreli, tek kullanımlık.
 */
class InvitationService
{
    public const EXPIRE_DAYS = 7;

    public function generatePlainToken(): string
    {
        return Str::random(64);
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function findByPlainToken(string $plainToken): ?User
    {
        if ($plainToken === '') {
            return null;
        }

        $hash = $this->hashToken($plainToken);

        $user = User::query()
            ->where('invitation_token', $hash)
            ->whereNull('invitation_accepted_at')
            ->first();

        if ($user) {
            return $user;
        }

        // Eski bcrypt hash'li davetler (deploy öncesi) — yavaş fallback
        $candidates = User::query()
            ->whereNotNull('invitation_token')
            ->whereNull('invitation_accepted_at')
            ->where('invited_at', '>=', now()->subDays(self::EXPIRE_DAYS + 1))
            ->limit(200)
            ->get();

        foreach ($candidates as $candidate) {
            $stored = (string) $candidate->invitation_token;
            if (strlen($stored) === 64 && ctype_xdigit($stored)) {
                continue;
            }
            if (Hash::check($plainToken, $stored)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isExpired(User $user): bool
    {
        if ($user->invited_at === null) {
            return true;
        }

        return $user->invited_at->lt(now()->subDays(self::EXPIRE_DAYS));
    }

    /**
     * @return array{email: string, name: string, company_name: string|null, expires_at: string}
     */
    public function preview(string $plainToken): array
    {
        $user = $this->findByPlainToken($plainToken);

        if (! $user || $user->invitation_accepted_at !== null) {
            throw ValidationException::withMessages([
                'token' => ['Davet geçersiz veya kullanılmış.'],
            ]);
        }

        if ($this->isExpired($user)) {
            throw ValidationException::withMessages([
                'token' => ['Davet süresi dolmuş. Yöneticinizden yeni davet isteyin.'],
            ]);
        }

        return [
            'email' => $user->email,
            'name' => $user->name,
            'company_name' => $user->company?->name,
            'expires_at' => $user->invited_at?->copy()->addDays(self::EXPIRE_DAYS)->toIso8601String() ?? '',
        ];
    }

    public function accept(string $plainToken, string $email, string $password): User
    {
        $user = $this->findByPlainToken($plainToken);

        if (! $user || $user->invitation_accepted_at !== null) {
            throw ValidationException::withMessages([
                'token' => ['Davet geçersiz veya kullanılmış.'],
            ]);
        }

        if ($this->isExpired($user)) {
            throw ValidationException::withMessages([
                'token' => ['Davet süresi dolmuş. Yöneticinizden yeni davet isteyin.'],
            ]);
        }

        if (strcasecmp($user->email, $email) !== 0) {
            throw ValidationException::withMessages([
                'email' => ['E-posta adresi davet ile eşleşmiyor.'],
            ]);
        }

        User::withoutAuditing(fn () => $user->update([
            'password' => $password,
            'is_active' => true,
            'invitation_token' => null,
            'invitation_accepted_at' => now(),
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]));

        $user->tokens()->delete();

        return $user->fresh();
    }

    /**
     * Yeni davet alanları (plain token ayrı döner — yalnızca mailde kullanılır).
     *
     * @return array{plain: string, hash: string, invited_at: \Illuminate\Support\Carbon}
     */
    public function issue(): array
    {
        $plain = $this->generatePlainToken();

        return [
            'plain' => $plain,
            'hash' => $this->hashToken($plain),
            'invited_at' => now(),
        ];
    }
}
