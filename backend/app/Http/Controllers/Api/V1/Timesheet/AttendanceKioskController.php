<?php

namespace App\Http\Controllers\Api\V1\Timesheet;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Timesheet\AttendanceKioskTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AttendanceKioskController extends BaseController
{
    public function __construct(
        protected AttendanceKioskTokenService $tokens,
    ) {}

    /**
     * Kısa ömürlü kiosk QR token üret (yenileme için polling).
     */
    public function issueToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli', 403);
        }

        try {
            $issued = $this->tokens->issue(
                (int) $companyId,
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                auth()->id() ? (int) auth()->id() : null,
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($issued, 'Kiosk token üretildi');
    }
}
