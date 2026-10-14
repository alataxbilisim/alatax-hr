<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Competency;
use App\Models\PositionCompetency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PositionCompetency>
 */
class PositionCompetencyFactory extends Factory
{
    protected $model = PositionCompetency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'position_name' => fake()->unique()->bothify('Ad-#####'),
            'competency_id' => fn (array $attributes) => Competency::factory()->state(['company_id' => $attributes['company_id']]),
            'expected_level' => fake()->numberBetween(1, 100),
        ];
    }
}
