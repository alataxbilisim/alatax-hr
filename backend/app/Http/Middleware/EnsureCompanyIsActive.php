<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Firmanın aktif olduğunu kontrol eden middleware
 */
class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // SuperAdmin kontrolden muaf
        if ($user && $user->type === UserType::SuperAdmin) {
            return $next($request);
        }

        // Kullanıcının firması var mı ve aktif mi?
        if ($user && $user->company) {
            // active ve trial durumları izin verilen durumlar
            $allowedStatuses = [CompanyStatus::Active, CompanyStatus::Trial];

            if (! in_array($user->company->status, $allowedStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firma hesabınız aktif değil. Lütfen yöneticinizle iletişime geçin.',
                    'data' => null,
                    'errors' => ['company' => 'Firma aktif değil'],
                    'timestamp' => now()->toDateTimeString(),
                ], 403);
            }
        }

        return $next($request);
    }
}
