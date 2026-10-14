<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ContinuousFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContinuousFeedback>
 */
class ContinuousFeedbackFactory extends Factory
{
    protected $model = ContinuousFeedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'from_user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'to_user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'content' => fake()->sentence(),
            'related_type' => fake()->word(),
            'related_id' => fake()->numberBetween(1, 100),
        ];
    }
}
