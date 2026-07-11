<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA challenge token'ı normal API'ye erişemesin (yalnızca /auth/2fa/verify).
 */
class DenyTwoFactorChallengeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if (! $token || ! isset($token->name)) {
            return $next($request);
        }

        $challengeNames = [
            TwoFactorService::CHALLENGE_TOKEN_NAME,
            '2fa-challenge-portal',
        ];

        if (in_array($token->name, $challengeNames, true)) {
            return response()->json([
                'success' => false,
                'message' => '2FA doğrulaması tamamlanmamış. Önce doğrulama kodunu girin.',
                'data' => null,
                'errors' => null,
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        return $next($request);
    }
}
