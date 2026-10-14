<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\OnboardingProcess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingProcess>
 */
class OnboardingProcessFactory extends Factory
{
    protected $model = OnboardingProcess::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'title' => fake()->sentence(3),
            'start_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
