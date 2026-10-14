<?php

namespace Database\Factories;

use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeyResult>
 */
class KeyResultFactory extends Factory
{
    protected $model = KeyResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'objective_id' => Objective::factory(),
            'owner_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => Objective::withoutGlobalScopes()->whereKey($attributes['objective_id'])->value('company_id')]),
            'title' => fake()->sentence(3),
            'target_value' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
