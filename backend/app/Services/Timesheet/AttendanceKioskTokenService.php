<?php

namespace App\Services\Timesheet;

use App\Models\AttendanceKioskToken;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Kısa ömürlü + tek kullanımlık + tenant bağlı kiosk QR token.
 *
 * QR içeriği: base64url(payload).hmac — payload: jti, company_id, branch_id, exp.
 */
class AttendanceKioskTokenService
{
    public const TTL_SECONDS = 30;

    public const QR_PREFIX = 'AXPDKS1';

    /**
     * @return array{token: string, expires_at: string, expires_in: int, company_id: int, branch_id: int|null}
     */
    public function issue(int $companyId, ?int $branchId, ?int $createdBy): array
    {
        if ($branchId !== null) {
            $ok = Branch::query()
                ->where('company_id', $companyId)
                ->where('id', $branchId)
                ->where('is_active', true)
                ->exists();
            if (! $ok) {
                throw new InvalidArgumentException('Şube bulunamadı veya bu firmaya ait değil');
            }
        }

        $jti = (string) Str::uuid();
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);
        $payload = [
            'v' => 1,
            'jti' => $jti,
            'cid' => $companyId,
            'bid' => $branchId,
            'exp' => $expiresAt->getTimestamp(),
        ];

        $payloadB64 = $this->b64Encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payloadB64, $this->signingKey());
        $rawToken = self::QR_PREFIX.'.'.$payloadB64.'.'.$signature;

        AttendanceKioskToken::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'jti' => $jti,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);

        return [
            'token' => $rawToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in' => self::TTL_SECONDS,
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ];
    }

    /**
     * Doğrula + tek kullanımlık tüket. Başarılıysa meta döner.
     *
     * @return array{company_id: int, branch_id: int|null, jti: string}
     */
    public function consume(string $rawToken, User $actor): array
    {
        $parsed = $this->parseAndVerifySignature($rawToken);

        if ($parsed['exp'] < now()->getTimestamp()) {
            throw new InvalidArgumentException('QR kodunun süresi dolmuş — yeni kodu okutun');
        }

        if ((int) $parsed['cid'] !== (int) $actor->company_id) {
            throw new InvalidArgumentException('Bu QR kodu başka bir firmaya ait');
        }

        return DB::transaction(function () use ($rawToken, $parsed, $actor): array {
            $row = AttendanceKioskToken::query()
                ->where('jti', $parsed['jti'])
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw new InvalidArgumentException('Geçersiz QR kodu');
            }

            if (! hash_equals($row->token_hash, hash('sha256', $rawToken))) {
                throw new InvalidArgumentException('Geçersiz QR kodu');
            }

            if ((int) $row->company_id !== (int) $actor->company_id) {
                throw new InvalidArgumentException('Bu QR kodu başka bir firmaya ait');
            }

            if ($row->used_at !== null) {
                throw new InvalidArgumentException('Bu QR kodu daha önce kullanılmış');
            }

            if ($row->expires_at->isPast()) {
                throw new InvalidArgumentException('QR kodunun süresi dolmuş — yeni kodu okutun');
            }

            $row->update([
                'used_at' => now(),
                'used_by_user_id' => $actor->id,
            ]);

            return [
                'company_id' => (int) $row->company_id,
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'jti' => (string) $row->jti,
            ];
        });
    }

    /**
     * @return array{v: int, jti: string, cid: int, bid: int|null, exp: int}
     */
    protected function parseAndVerifySignature(string $rawToken): array
    {
        $parts = explode('.', $rawToken);
        if (count($parts) !== 3 || $parts[0] !== self::QR_PREFIX) {
            throw new InvalidArgumentException('Geçersiz QR formatı');
        }

        [$prefix, $payloadB64, $signature] = $parts;
        unset($prefix);

        $expected = hash_hmac('sha256', $payloadB64, $this->signingKey());
        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Geçersiz QR imzası');
        }

        try {
            $json = $this->b64Decode($payloadB64);
            /** @var array{v?: mixed, jti?: mixed, cid?: mixed, bid?: mixed, exp?: mixed} $payload */
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Geçersiz QR içeriği');
        }

        if (! isset($payload['jti'], $payload['cid'], $payload['exp']) || ! is_string($payload['jti'])) {
            throw new InvalidArgumentException('Geçersiz QR içeriği');
        }

        return [
            'v' => (int) ($payload['v'] ?? 1),
            'jti' => $payload['jti'],
            'cid' => (int) $payload['cid'],
            'bid' => isset($payload['bid']) && $payload['bid'] !== null ? (int) $payload['bid'] : null,
            'exp' => (int) $payload['exp'],
        ];
    }

    protected function signingKey(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY tanımlı değil');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    protected function b64Encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function b64Decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Geçersiz QR içeriği');
        }

        return $decoded;
    }
}
