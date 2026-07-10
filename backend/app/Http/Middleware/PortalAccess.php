<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Kullanıcı var mı?
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Kimlik doğrulama gerekli',
            ], 401);
        }

        // Employee kaydı var mı?
        if (! $user->employee) {
            return response()->json([
                'success' => false,
                'message' => 'Portal erişim yetkiniz yok',
            ], 403);
        }

        // Employee aktif mi?
        if ($user->employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Personel kaydınız aktif değil',
            ], 403);
        }

        return $next($request);
    }
}
