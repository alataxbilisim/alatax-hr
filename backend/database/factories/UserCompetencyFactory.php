<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Competency;
use App\Models\User;
use App\Models\UserCompetency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCompetency>
 */
class UserCompetencyFactory extends Factory
{
    protected $model = UserCompetency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'competency_id' => fn (array $attributes) => Competency::factory()->state(['company_id' => $attributes['company_id']]),
            'current_level' => fake()->numberBetween(1, 100),
            'assessed_at' => fake()->date(),
        ];
    }
}
