<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Firma Admini veya SuperAdmin erişimi için middleware
 */
class CompanyAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Oturum açmanız gerekmektedir.',
                'data' => null,
                'errors' => ['authentication' => 'Oturum bulunamadı'],
                'timestamp' => now()->toDateTimeString(),
            ], 401);
        }

        // SuperAdmin ve Company Admin izinli
        if (! in_array($user->type, ['super_admin', 'company_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Bu işlem için yönetici yetkisi gereklidir.',
                'data' => null,
                'errors' => ['authorization' => 'Yetersiz yetki'],
                'timestamp' => now()->toDateTimeString(),
            ], 403);
        }

        return $next($request);
    }
}
