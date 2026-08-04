<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-Company-Id doğrular ve CompanyContext'i container'a bağlar (G1).
 * SuperAdmin / şirketsiz kullanıcı: bağlam bağlanmaz.
 */
class ResolveCompanyContext
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Önceki istekten kalan stale bağlamı temizle (özellikle Feature test process'i)
        \App\Support\CompanyContext::forget();

        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->type === UserType::SuperAdmin) {
            return $next($request);
        }

        // Portal: şirket seçici yok — home company ile bağla (header yoksa fallback)
        // Membership zorunlu; sahte header yine 403.
        if ($user->company_id === null
            && empty($this->companyContext->accessibleCompanyIds($user))) {
            return $next($request);
        }

        $this->companyContext->resolveFromRequest($request, $user);

        return $next($request);
    }
}
