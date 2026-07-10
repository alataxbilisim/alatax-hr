<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    public function definition(): array
    {
        $types = ['engagement', 'satisfaction', 'pulse', 'enps', 'onboarding', 'exit', 'custom'];

        return [
            'company_id' => Company::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'type' => $this->faker->randomElement($types),
            'is_anonymous' => $this->faker->boolean(70),
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonth(),
        ]);
    }
}
