<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Firmanın aktif olduğunu kontrol eden middleware.
 * Aktif bağlam varsa onu; yoksa home company'yi kontrol eder.
 */
class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->type === UserType::SuperAdmin) {
            return $next($request);
        }

        $company = null;
        if (CompanyContext::isBound()) {
            $company = Company::query()->find(CompanyContext::id());
        } elseif ($user) {
            $company = $user->company;
        }

        if ($company) {
            $allowedStatuses = [CompanyStatus::Active, CompanyStatus::Trial];

            if (! in_array($company->status, $allowedStatuses, true)) {
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
