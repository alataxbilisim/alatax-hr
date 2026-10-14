<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\OnboardingProcess;
use App\Models\OnboardingTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTask>
 */
class OnboardingTaskFactory extends Factory
{
    protected $model = OnboardingTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'process_id' => fn (array $attributes) => OnboardingProcess::factory()->state(['company_id' => $attributes['company_id']]),
            'title' => fake()->sentence(3),
        ];
    }
}
