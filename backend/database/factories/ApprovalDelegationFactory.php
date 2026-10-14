<?php

namespace Database\Factories;

use App\Models\ApprovalDelegation;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalDelegation>
 */
class ApprovalDelegationFactory extends Factory
{
    protected $model = ApprovalDelegation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'delegator_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'delegate_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'start_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
        ];
    }
}
