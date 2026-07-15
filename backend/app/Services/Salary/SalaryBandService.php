<?php

namespace App\Services\Salary;

use App\Models\Employee;
use App\Models\Position;
use App\Models\SalaryBand;

/**
 * Pozisyon ücret bandı çözümleme (null-safe).
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
        $empty = [
            'band' => null,
            'position' => $employee->position,
            'status' => null,
            'ratio' => null,
        ];

        if ($amount === null || $employee->position === null || trim((string) $employee->position) === '') {
            return $empty;
        }

        $position = Position::query()
            ->where('company_id', $employee->company_id)
            ->where('name', $employee->position)
            ->where('is_active', true)
            ->first();

        if ($position === null) {
            return $empty;
        }

        $band = SalaryBand::query()
            ->where('company_id', $employee->company_id)
            ->where('position_id', $position->id)
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
                'position_name' => $position->name,
            ],
            'position' => $employee->position,
            'status' => $status,
            'ratio' => round($ratio, 4),
        ];
    }
}
