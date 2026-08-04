<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Timesheet\AttendanceClockService;
use App\Services\Timesheet\AttendanceKioskTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PortalAttendanceQrController extends BaseController
{
    public function __construct(
        protected AttendanceKioskTokenService $tokens,
        protected AttendanceClockService $clock,
    ) {}

    /**
     * QR token okut → giriş veya çıkış (punch).
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:2048',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $user = $request->user();
        if (! $user || ! $user->company_id) {
            return $this->error('Oturum gerekli', 401);
        }

        try {
            $meta = $this->tokens->consume($validated['token'], $user);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        try {
            $result = $this->clock->punch($user, [
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'ip' => $request->ip(),
                'method' => 'qr',
                'source' => AttendanceClockService::SOURCE_QR,
                'branch_id' => $meta['branch_id'],
                'device_info' => $request->userAgent(),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $action = $result['action'];
        $time = $result['clock_time'];
        $message = $action === 'clock_in'
            ? "Giriş kaydedildi {$time}"
            : "Çıkış kaydedildi {$time}";

        return $this->success([
            'action' => $action,
            'clock_time' => $time,
            'source' => AttendanceClockService::SOURCE_QR,
            'branch_id' => $meta['branch_id'],
            'record' => $result['record'],
            'message' => $message,
        ], $message);
    }
}
