<?php

namespace App\Services\Timesheet;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\ActivityLog;
use InvalidArgumentException;

/**
 * Portal / QR / manuel ortak giriş-çıkış mantığı.
 */
class AttendanceClockService
{
    public const SOURCE_PORTAL = 'portal';

    public const SOURCE_QR = 'qr';

    public const SOURCE_MANUAL = 'manual';

    /**
     * @param  array{
     *   latitude?: float|null,
     *   longitude?: float|null,
     *   ip?: string|null,
     *   method?: string|null,
     *   source?: string|null,
     *   branch_id?: int|null,
     *   device_info?: string|null,
     * }  $meta
     * @return array{action: 'clock_in', record: AttendanceRecord, clock_time: string}
     */
    public function clockIn(User $user, array $meta = []): array
    {
        $today = now()->toDateString();
        $existing = AttendanceRecord::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->clock_in) {
            throw new InvalidArgumentException('Bugün zaten giriş yapmışsınız');
        }

        $method = $meta['method'] ?? 'mobile';
        $source = $meta['source'] ?? self::SOURCE_PORTAL;

        $data = [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'date' => $today,
            'clock_in' => now()->format('H:i'),
            'clock_in_method' => $method,
            'clock_in_latitude' => $meta['latitude'] ?? null,
            'clock_in_longitude' => $meta['longitude'] ?? null,
            'clock_in_ip' => $meta['ip'] ?? null,
            'status' => AttendanceRecord::STATUS_PRESENT,
            'source' => $source,
            'branch_id' => $meta['branch_id'] ?? null,
            'device_info' => $this->truncateDevice($meta['device_info'] ?? null),
        ];

        if ($existing) {
            $existing->update($data);
            $record = $existing->fresh();
        } else {
            $record = AttendanceRecord::create($data);
        }

        ActivityLog::log('clock_in', $record, 'Giriş yapıldı');

        return [
            'action' => 'clock_in',
            'record' => $record,
            'clock_time' => $this->formatTime($record->clock_in),
        ];
    }

    /**
     * @param  array{
     *   latitude?: float|null,
     *   longitude?: float|null,
     *   ip?: string|null,
     *   method?: string|null,
     *   source?: string|null,
     *   branch_id?: int|null,
     *   device_info?: string|null,
     * }  $meta
     * @return array{action: 'clock_out', record: AttendanceRecord, clock_time: string}
     */
    public function clockOut(User $user, array $meta = []): array
    {
        $today = now()->toDateString();
        $record = AttendanceRecord::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $record || ! $record->clock_in) {
            throw new InvalidArgumentException('Önce giriş yapmalısınız');
        }

        if ($record->clock_out) {
            throw new InvalidArgumentException('Bugün zaten çıkış yapmışsınız');
        }

        $method = $meta['method'] ?? 'mobile';
        $source = $meta['source'] ?? ($record->source ?: self::SOURCE_PORTAL);

        $record->update([
            'clock_out' => now()->format('H:i'),
            'clock_out_method' => $method,
            'clock_out_latitude' => $meta['latitude'] ?? null,
            'clock_out_longitude' => $meta['longitude'] ?? null,
            'clock_out_ip' => $meta['ip'] ?? null,
            'source' => $source,
            'branch_id' => $meta['branch_id'] ?? $record->branch_id,
            'device_info' => $this->truncateDevice($meta['device_info'] ?? $record->device_info),
        ]);

        $record->total_hours = $record->calculateTotalHours();
        $record->save();

        ActivityLog::log('clock_out', $record, 'Çıkış yapıldı');

        return [
            'action' => 'clock_out',
            'record' => $record->fresh(),
            'clock_time' => $this->formatTime($record->clock_out),
        ];
    }

    /**
     * İçeride değilse giriş, içerideyse çıkış.
     *
     * @param  array<string, mixed>  $meta
     * @return array{action: 'clock_in'|'clock_out', record: AttendanceRecord, clock_time: string}
     */
    public function punch(User $user, array $meta = []): array
    {
        $today = now()->toDateString();
        $existing = AttendanceRecord::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->clock_in && ! $existing->clock_out) {
            return $this->clockOut($user, $meta);
        }

        return $this->clockIn($user, $meta);
    }

    protected function truncateDevice(?string $info): ?string
    {
        if ($info === null || $info === '') {
            return null;
        }

        return mb_substr($info, 0, 255);
    }

    protected function formatTime(mixed $value): string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('H:i');
        }

        return (string) $value;
    }
}
