<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Auth endpoint throttle (10/dk per IP) — brute-force koruması.
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_429_after_auth_rate_limit(): void
    {
        RateLimiter::clear(md5('auth'.request()->ip()));

        $payload = [
            'email' => 'nobody@example.com',
            'password' => 'WrongPass1!',
        ];

        // 10 deneme: limiter henüz 429 vermemeli (401/422 beklenir)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/auth/login', $payload);
            $this->assertNotEquals(429, $response->status(), "Deneme #{$i} beklenmedik 429");
        }

        // 11. deneme: 429
        $blocked = $this->postJson('/api/v1/auth/login', $payload);
        $blocked->assertStatus(429);
    }
}
