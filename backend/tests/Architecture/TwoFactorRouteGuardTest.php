<?php

namespace Tests\Architecture;

use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * `auth:sanctum` kullanan her route `deny.2fa.challenge` içermeli; aksi halde
 * 2FA challenge token'ı o uca erişebilir. Tek bilinçli istisna doğrulama ucunun kendisi.
 */
class TwoFactorRouteGuardTest extends TestCase
{
    /** Route anahtarı: "METHOD uri". */
    private const ALLOWED_WITHOUT_GUARD = [
        'POST api/v1/auth/2fa/verify' => 'Challenge token yalnız bu uçta geçerli (ability:2fa-challenge).',
    ];

    public function test_every_sanctum_route_denies_two_factor_challenge_tokens(): void
    {
        $missing = [];

        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            $middleware = $route->gatherMiddleware();
            if (! in_array('auth:sanctum', $middleware, true) || in_array('deny.2fa.challenge', $middleware, true)) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.$route->uri();
                if (! array_key_exists($key, self::ALLOWED_WITHOUT_GUARD)) {
                    $missing[] = $key;
                }
            }
        }

        $this->assertSame([], $missing, "deny.2fa.challenge eksik route'lar:\n".implode("\n", $missing));
    }

    public function test_allow_list_entries_still_exist(): void
    {
        $existing = [];
        foreach (app('router')->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $existing[$method.' '.$route->uri()] = true;
            }
        }

        foreach (array_keys(self::ALLOWED_WITHOUT_GUARD) as $key) {
            $this->assertArrayHasKey($key, $existing, "İzin listesindeki route artık yok, listeden çıkarın: {$key}");
        }
    }
}
