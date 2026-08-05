<?php

namespace App\Services\Salary;

use App\Models\Employee;
use App\Models\SalaryBand;

/**
 * Pozisyon ücret bandı çözümleme (null-safe).
 * position_id SSOT — string kolon yok (§4).
 */
class SalaryBandService
{
    /**
     * @return array{
     *   band: array<string, mixed>|null,
     *   position: string|null,
     *   status: 'below'|'within'|'above'|null,
     *   ratio: float|null
     * }
     */
    public function indicatorForEmployee(Employee $employee, mixed $amount): array
    {
        $employee->loadMissing('position');
        $positionName = $employee->position?->name;

        $empty = [
            'band' => null,
            'position' => $positionName,
            'status' => null,
            'ratio' => null,
        ];

        if ($amount === null || $employee->position_id === null) {
            return $empty;
        }

        $band = SalaryBand::query()
            ->where('company_id', $employee->company_id)
            ->where('position_id', $employee->position_id)
            ->where('is_active', true)
            ->first();

        if ($band === null) {
            return $empty;
        }

        $value = (float) $amount;
        $min = (float) $band->min_amount;
        $max = (float) $band->max_amount;

        if ($value < $min) {
            $status = 'below';
        } elseif ($value > $max) {
            $status = 'above';
        } else {
            $status = 'within';
        }

        $span = $max - $min;
        $ratio = $span > 0 ? max(0, min(1, ($value - $min) / $span)) : 0.5;

        return [
            'band' => [
                'id' => $band->id,
                'min_amount' => $band->min_amount,
                'mid_amount' => $band->mid_amount,
                'max_amount' => $band->max_amount,
                'currency' => $band->currency,
                'position_id' => $band->position_id,
                'position_name' => $positionName,
            ],
            'position' => $positionName,
            'status' => $status,
            'ratio' => round($ratio, 4),
        ];
    }
}
