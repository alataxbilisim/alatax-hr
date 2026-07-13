<?php

namespace App\Providers;

use App\Enums\UserType;
use App\Events\ApprovalRequested;
use App\Listeners\SendApprovalRequestedNotification;
use App\Models\User;
use App\Support\HierarchicalPermission;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->configureAuthorizationGates();

        Event::listen(ApprovalRequested::class, SendApprovalRequestedNotification::class);
    }

    /**
     * Spatie permission: middleware için Gate::before —
     * super_admin type bypass + hiyerarşik wildcard (employees.* → employees.list.view).
     * company_admin yetkisi Spatie 'admin' rolünden gelir (type bypass yok).
     */
    protected function configureAuthorizationGates(): void
    {
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            // Platform sahibi — Spatie rolünden bağımsız
            if ($user->type === UserType::SuperAdmin) {
                return true;
            }

            // Hiyerarşik izin string'leri (module.page.action); diğer ability'ler Spatie/default'a bırakılır
            if (! str_contains($ability, '.')) {
                return null;
            }

            $permissionNames = $user->getAllPermissions()->pluck('name')->all();

            if (HierarchicalPermission::matches($permissionNames, $ability)) {
                return true;
            }

            return null;
        });
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
