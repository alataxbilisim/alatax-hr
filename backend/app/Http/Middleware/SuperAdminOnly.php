<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sadece SuperAdmin erişimi için middleware
 */
class SuperAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Bu işlem için SuperAdmin yetkisi gereklidir.',
                'data' => null,
                'errors' => ['authorization' => 'Yetersiz yetki'],
                'timestamp' => now()->toDateTimeString(),
            ], 403);
        }

        return $next($request);
    }
}
