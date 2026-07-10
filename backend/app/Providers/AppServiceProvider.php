<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Named rate limiters — route gruplarında throttle:{name} ile kullanılır.
     */
    protected function configureRateLimiting(): void
    {
        // Auth (login/register/forgot/reset) — brute-force koruması, IP bazlı
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Genel API — auth'lu isteklerde user, değilse IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Export / import / ağır rapor — kullanıcı başına
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Public kariyer / başvuru — IP bazlı
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
