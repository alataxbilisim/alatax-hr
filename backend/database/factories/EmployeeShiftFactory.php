<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeShift>
 */
class EmployeeShiftFactory extends Factory
{
    protected $model = EmployeeShift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'shift_id' => fn (array $attributes) => Shift::factory()->state(['company_id' => $attributes['company_id']]),
            'date' => fake()->date(),
        ];
    }
}
