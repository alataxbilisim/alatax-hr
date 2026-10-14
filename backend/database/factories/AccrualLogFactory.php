<?php

namespace Database\Factories;

use App\Models\AccrualLog;
use App\Models\Company;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccrualLog>
 */
class AccrualLogFactory extends Factory
{
    protected $model = AccrualLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'leave_type_id' => fn (array $attributes) => LeaveType::factory()->state(['company_id' => $attributes['company_id']]),
            'type' => 'accrual',
            'amount' => fake()->randomFloat(2, 0, 1000),
            'balance_before' => fake()->randomFloat(2, 0, 1000),
            'balance_after' => fake()->randomFloat(2, 0, 1000),
            'effective_date' => fake()->date(),
            'reference_type' => fake()->word(),
            'reference_id' => fake()->numberBetween(1, 100),
        ];
    }
}
