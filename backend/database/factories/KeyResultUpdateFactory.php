<?php

namespace Database\Factories;

use App\Models\KeyResult;
use App\Models\KeyResultUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeyResultUpdate>
 */
class KeyResultUpdateFactory extends Factory
{
    protected $model = KeyResultUpdate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key_result_id' => KeyResult::factory(),
            'user_id' => User::factory(),
            'previous_value' => fake()->randomFloat(2, 0, 1000),
            'new_value' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
