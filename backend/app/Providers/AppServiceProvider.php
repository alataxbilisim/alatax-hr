<?php

namespace App\Providers;

use App\Enums\UserType;
use App\Models\User;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Rules\CompanyMember;
use App\Rules\ExistsInCompany;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsResolver;
use App\Support\HierarchicalPermission;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel çerçeve varsayılan DB bağlantılarını config/database.php ile birleştirir;
        // yalnız pgsql desteklendiği için diğerleri burada düşürülür.
        config(['database.connections' => array_intersect_key(
            (array) config('database.connections', []),
            ['pgsql' => true],
        )]);

        $this->app->bind(DatabaseChannel::class, TenantDatabaseChannel::class);
        // D4a — istek başına çözümleme cache'i için singleton
        $this->app->singleton(SettingsRegistry::class);
        $this->app->singleton(SettingsResolver::class);
        $this->app->singleton(\App\Services\Kvkk\PersonalData\PersonalDataCollectorRegistry::class);
        // W1 — onaya tabi entity kataloğu
        $this->app->singleton(\App\Services\Approval\ApprovalEntityRegistry::class);
        // D3 — EmployeeResource resolve() başına DB yerine istek içi memo
        $this->app->singleton(\App\Services\LookupService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthorizationGates();
        $this->configureTenantValidationRules();

        // Test ortamında .env/config:cache log mailer'ı ezmesin (queued Mailable)
        if ($this->app->environment('testing')) {
            config([
                'mail.default' => 'array',
                'queue.default' => 'sync',
            ]);
        }
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
     * Tenant tablolarına `exists:` yerine (TenantExistsRuleGuardTest zorunlu kılar):
     * `company_exists:tablo[,kolon][,global]` ve kullanıcılar için `company_member[:active]`.
     */
    protected function configureTenantValidationRules(): void
    {
        Validator::extend('company_exists', function (string $attribute, mixed $value, array $parameters): bool {
            $table = $parameters[0] ?? '';
            $column = isset($parameters[1]) && $parameters[1] !== 'global' ? $parameters[1] : 'id';

            return $table !== '' && ExistsInCompany::passes($table, $value, $column, in_array('global', $parameters, true));
        });

        Validator::extend('company_member', function (string $attribute, mixed $value, array $parameters): bool {
            return CompanyMember::passes($value, in_array('active', $parameters, true));
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
