<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DestructionCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestructionCandidate>
 */
class DestructionCandidateFactory extends Factory
{
    protected $model = DestructionCandidate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject_type' => mb_substr(fake()->sentence(3), 0, 32),
            'subject_id' => fake()->numberBetween(1, 100),
            'data_category' => fake()->word(),
        ];
    }
}
