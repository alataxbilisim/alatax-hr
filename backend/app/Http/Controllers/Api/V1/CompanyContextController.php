<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyContextController extends BaseController
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    /**
     * Kullanıcının erişebildiği şirketler + aktif bağlam.
     */
    public function companies(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorized();
        }

        return $this->success($this->companyContext->availableFor($user));
    }
}
