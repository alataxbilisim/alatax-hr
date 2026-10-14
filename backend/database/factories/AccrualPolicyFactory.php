<?php

namespace Database\Factories;

use App\Models\AccrualPolicy;
use App\Models\Company;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccrualPolicy>
 */
class AccrualPolicyFactory extends Factory
{
    protected $model = AccrualPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'leave_type_id' => fn (array $attributes) => LeaveType::factory()->state(['company_id' => $attributes['company_id']]),
            'name' => ucfirst(fake()->words(2, true)),
            'accrual_rate' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
