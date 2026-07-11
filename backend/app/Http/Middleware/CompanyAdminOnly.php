<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Firma Admini veya SuperAdmin erişimi için middleware.
 *
 * Dalga 4 geçiş: UserType::user engellenmez — asıl yetki route'taki
 * permission: middleware + Gate::before (company_admin type bypass) ile verilir.
 * Böylece Spatie rolü olan (hr_manager vb.) user type kullanıcılar da erişebilir;
 * company_admin type ise Gate bypass ile permission'sız geçer.
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

        // SuperAdmin / CompanyAdmin: type bypass (Gate::before ile permission da geçer)
        if (in_array($user->type, [UserType::SuperAdmin, UserType::CompanyAdmin], true)) {
            return $next($request);
        }

        // UserType::user: company_admin type yok — permission middleware karar verir
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
