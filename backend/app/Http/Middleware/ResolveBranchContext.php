<?php

namespace App\Http\Middleware;

use App\Services\BranchContextService;
use App\Support\BranchContext;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-Branch-Id doğrular ve BranchContext'i container'a bağlar.
 * Employee filtresi model global scope üzerinden okunur (birikmez).
 * Aktif şirket (CompanyContext) yoksa atlanır.
 */
class ResolveBranchContext
{
    public const SCOPE_NAME = 'branch_context';

    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound(BranchContext::class)) {
            app()->forgetInstance(BranchContext::class);
        }

        $user = $request->user();
        $companyId = CompanyContext::id() ?? $user?->company_id;
        if ($user === null || $companyId === null) {
            return $next($request);
        }

        $context = $this->branchContext->resolveFromRequest($request, $user);
        app()->instance(BranchContext::class, $context);

        return $next($request);
    }
}
