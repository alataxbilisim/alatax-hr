<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LegalHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalHold>
 */
class LegalHoldFactory extends Factory
{
    protected $model = LegalHold::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject_type' => mb_substr(fake()->sentence(3), 0, 32),
            'subject_id' => fake()->numberBetween(1, 100),
            'reason' => fake()->sentence(),
            'placed_by' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'placed_at' => now(),
        ];
    }
}
