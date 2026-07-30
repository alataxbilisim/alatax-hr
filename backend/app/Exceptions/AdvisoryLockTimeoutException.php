<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Advisory / row lock timeout — 423 Locked (ApiResponse şekli).
 */
class AdvisoryLockTimeoutException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Kayıt şu an başka bir işlem tarafından kilitli. Lütfen kısa süre sonra tekrar deneyin.',
            'data' => null,
            'errors' => ['lock' => ['advisory_lock_timeout']],
            'timestamp' => now()->toIso8601String(),
        ], 423);
    }

    public function report(): bool
    {
        // Beklenen yarış — log gürültüsü olmasın
        return false;
    }
}
