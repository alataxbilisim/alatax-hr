<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API isteklerinde web login route'una yönlendirme yapma (401 JSON dönsün)
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return null;
        });

        // Middleware aliases
        $middleware->alias([
            'company.active' => \App\Http\Middleware\EnsureCompanyIsActive::class,
            'module.access' => \App\Http\Middleware\CheckModuleAccess::class,
            'super_admin' => \App\Http\Middleware\SuperAdminOnly::class,
            'company_admin' => \App\Http\Middleware\CompanyAdminOnly::class,
            'portal.access' => \App\Http\Middleware\PortalAccess::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Named limiter: AppServiceProvider → RateLimiter::for('api') = 120/dk
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API exception handling
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
