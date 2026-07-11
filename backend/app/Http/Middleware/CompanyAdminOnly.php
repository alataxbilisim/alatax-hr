<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Firma yönetimi route grubuna soft giriş.
 *
 * UserType::user engellenmez — asıl yetki route'taki permission: middleware
 * + Spatie rollerinden gelir. company_admin type yalnızca grup soft-pass;
 * izinler Spatie 'admin' rolü ile verilir (Gate type bypass yok).
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

        // SuperAdmin / CompanyAdmin: soft-pass (izinler permission middleware'de)
        if (in_array($user->type, [UserType::SuperAdmin, UserType::CompanyAdmin], true)) {
            return $next($request);
        }

        // UserType::user: Spatie rol (hr_manager vb.) + permission middleware
        if ($user->type === UserType::User) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Bu işlem için yönetici yetkisi gereklidir.',
            'data' => null,
            'errors' => ['authorization' => 'Yetersiz yetki'],
            'timestamp' => now()->toDateTimeString(),
        ], 403);
    }
}
