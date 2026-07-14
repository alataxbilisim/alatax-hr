<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchContextController extends BaseController
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    /**
     * Kullanıcının seçebileceği şubeler + tüm şubeler hakkı.
     */
    public function branches(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorized();
        }

        return $this->success($this->branchContext->availableFor($user));
    }
}
