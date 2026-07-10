<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modül erişim kontrolü middleware
 * Firmanın ilgili modüle erişimi olup olmadığını kontrol eder
 */
class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $user = $request->user();

        // SuperAdmin tüm modüllere erişebilir
        if ($user && $user->type === 'super_admin') {
            return $next($request);
        }

        // Kullanıcının firması bu modüle erişebilir mi?
        if ($user && $user->company) {
            $hasModule = $user->company->modules()
                ->where('slug', $moduleSlug)
                ->where('company_modules.is_active', true)
                ->exists();

            if (! $hasModule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu modüle erişim yetkiniz bulunmamaktadır.',
                    'data' => null,
                    'errors' => ['module' => 'Modül aktif değil veya satın alınmamış'],
                    'timestamp' => now()->toDateTimeString(),
                ], 403);
            }
        }

        return $next($request);
    }
}
