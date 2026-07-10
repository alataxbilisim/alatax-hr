<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $clockIn = $this->faker->time('H:i', '10:00');
        $clockOut = $this->faker->time('H:i', '18:00');

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'total_hours' => 8,
            'overtime_hours' => 0,
            'clock_in_method' => 'manual',
            'clock_out_method' => 'manual',
            'status' => 'present',
            'is_approved' => false,
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'late',
            'clock_in' => '10:30',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
            'approved_at' => now(),
        ]);
    }
}

