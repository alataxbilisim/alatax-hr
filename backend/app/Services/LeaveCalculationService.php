<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\AccrualPolicy;
use App\Models\AccrualLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class LeaveCalculationService
{
    /**
     * İki tarih arasındaki iş günü sayısını hesapla
     */
    public function calculateWorkingDays(
        Carbon $startDate,
        Carbon $endDate,
        ?int $companyId = null,
        bool $excludeWeekends = true,
        bool $excludeHolidays = true
    ): float {
        $days = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $isWorkingDay = true;

            // Hafta sonu kontrolü
            if ($excludeWeekends && $current->isWeekend()) {
                $isWorkingDay = false;
            }

            // Tatil kontrolü
            if ($isWorkingDay && $excludeHolidays) {
                if (Holiday::isHoliday($current, $companyId)) {
                    $isWorkingDay = false;
                }
            }

            if ($isWorkingDay) {
                $days++;
            }

            $current->addDay();
        }

        return $days;
    }

    /**
     * Çakışma kontrolü
     */
    public function checkConflict(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $excludeRequestId = null
    ): bool {
        $query = \App\Models\LeaveRequest::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        if ($excludeRequestId) {
            $query->where('id', '!=', $excludeRequestId);
        }

        return $query->exists();
    }

    /**
     * Departman çakışma kontrolü
     */
    public function checkDepartmentConflict(
        int $departmentId,
        Carbon $startDate,
        Carbon $endDate,
        float $maxPercentage = 0.5
    ): array {
        $departmentUserIds = User::where('department_id', $departmentId)->pluck('id');
        $totalUsers = $departmentUserIds->count();

        if ($totalUsers === 0) {
            return ['conflict' => false, 'percentage' => 0, 'users_on_leave' => 0];
        }

        // Bu tarih aralığında izinli olan kullanıcıları say
        $usersOnLeave = \App\Models\LeaveRequest::whereIn('user_id', $departmentUserIds)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->distinct('user_id')
            ->count('user_id');

        $percentage = $usersOnLeave / $totalUsers;

        return [
            'conflict' => $percentage >= $maxPercentage,
            'percentage' => round($percentage * 100, 2),
            'users_on_leave' => $usersOnLeave,
            'total_users' => $totalUsers,
        ];
    }

    /**
     * Kullanıcının bakiye kontrolü
     */
    public function checkBalance(int $userId, int $leaveTypeId, float $requestedDays, int $year): array
    {
        $balance = LeaveBalance::where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            return [
                'sufficient' => false,
                'available' => 0,
                'requested' => $requestedDays,
                'message' => 'İzin bakiyesi bulunamadı',
            ];
        }

        $available = $balance->available_days;
        $leaveType = LeaveType::find($leaveTypeId);

        // Negatif bakiye izni kontrolü
        $accrualPolicy = $leaveType?->accrualPolicy;
        $minBalance = $accrualPolicy?->min_balance ?? 0;

        $sufficient = ($available - $requestedDays) >= $minBalance;

        return [
            'sufficient' => $sufficient,
            'available' => $available,
            'requested' => $requestedDays,
            'remaining_after' => $available - $requestedDays,
            'min_balance' => $minBalance,
            'message' => $sufficient ? 'Yeterli bakiye' : 'Yetersiz bakiye',
        ];
    }

    /**
     * Aylık hakediş işlemi (Cron job ile çalışır)
     */
    public function processMonthlyAccruals(int $companyId, int $month, int $year): array
    {
        $results = [];
        
        $policies = AccrualPolicy::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('accrual_type', 'monthly')
            ->get();

        foreach ($policies as $policy) {
            $leaveType = $policy->leaveType;
            
            // Bu izin tipine sahip tüm kullanıcıları al
            $balances = LeaveBalance::where('company_id', $companyId)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->get();

            foreach ($balances as $balance) {
                $user = $balance->user;
                if (!$user || !$user->is_active) {
                    continue;
                }

                // Kıdem hesapla
                $yearsOfService = $user->hire_date 
                    ? Carbon::parse($user->hire_date)->diffInYears(now())
                    : 0;

                // Aylık birikim miktarı
                $accrualAmount = $policy->getMonthlyAccrual($yearsOfService);

                if ($accrualAmount <= 0) {
                    continue;
                }

                // Maksimum bakiye kontrolü
                $newBalance = $balance->total_days + $accrualAmount;
                if ($policy->max_balance && $newBalance > $policy->max_balance) {
                    $accrualAmount = max(0, $policy->max_balance - $balance->total_days);
                }

                if ($accrualAmount <= 0) {
                    continue;
                }

                // Bakiyeyi güncelle
                $oldBalance = $balance->total_days;
                $balance->total_days += $accrualAmount;
                $balance->accrued += $accrualAmount;
                $balance->save();

                // Log kaydı
                AccrualLog::createLog(
                    $companyId,
                    $user->id,
                    $leaveType->id,
                    AccrualLog::TYPE_ACCRUAL,
                    $accrualAmount,
                    $oldBalance,
                    "Aylık hakediş ({$month}/{$year})",
                    $balance,
                    $policy->id,
                    $balance->id
                );

                $results[] = [
                    'user_id' => $user->id,
                    'leave_type_id' => $leaveType->id,
                    'accrued' => $accrualAmount,
                ];
            }
        }

        return $results;
    }

    /**
     * Yıl sonu devir işlemi
     */
    public function processYearEndCarryover(int $companyId, int $fromYear): array
    {
        $toYear = $fromYear + 1;
        $results = [];

        $policies = AccrualPolicy::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('allow_carryover', true)
            ->get();

        foreach ($policies as $policy) {
            $leaveType = $policy->leaveType;

            $balances = LeaveBalance::where('company_id', $companyId)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $fromYear)
                ->get();

            foreach ($balances as $balance) {
                $unusedDays = $balance->available_days;
                $carryoverAmount = $policy->calculateCarryover($unusedDays);

                if ($carryoverAmount <= 0) {
                    continue;
                }

                // Yeni yıl bakiyesini bul veya oluştur
                $newYearBalance = LeaveBalance::firstOrCreate(
                    [
                        'company_id' => $companyId,
                        'user_id' => $balance->user_id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $toYear,
                    ],
                    [
                        'total_days' => 0,
                        'used_days' => 0,
                        'pending_days' => 0,
                        'carried_over' => 0,
                        'accrued' => 0,
                    ]
                );

                $oldBalance = $newYearBalance->total_days;
                $newYearBalance->total_days += $carryoverAmount;
                $newYearBalance->carried_over = $carryoverAmount;
                $newYearBalance->carryover_expiry = $policy->carryover_expiry_date;
                $newYearBalance->save();

                // Eski yıl bakiyesinde expired olarak işaretle
                $expiredAmount = $unusedDays - $carryoverAmount;
                if ($expiredAmount > 0) {
                    $balance->expired = $expiredAmount;
                    $balance->save();

                    AccrualLog::createLog(
                        $companyId,
                        $balance->user_id,
                        $leaveType->id,
                        AccrualLog::TYPE_EXPIRY,
                        -$expiredAmount,
                        $unusedDays,
                        "Yıl sonu süre dolumu ({$fromYear})",
                        $balance,
                        $policy->id,
                        $balance->id
                    );
                }

                // Devir log kaydı
                AccrualLog::createLog(
                    $companyId,
                    $balance->user_id,
                    $leaveType->id,
                    AccrualLog::TYPE_CARRYOVER,
                    $carryoverAmount,
                    $oldBalance,
                    "Yıl sonu devir ({$fromYear} -> {$toYear})",
                    $newYearBalance,
                    $policy->id,
                    $newYearBalance->id
                );

                $results[] = [
                    'user_id' => $balance->user_id,
                    'leave_type_id' => $leaveType->id,
                    'carried_over' => $carryoverAmount,
                    'expired' => $expiredAmount,
                ];
            }
        }

        return $results;
    }
}


