<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DestructionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestructionLog>
 */
class DestructionLogFactory extends Factory
{
    protected $model = DestructionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject_type' => mb_substr(fake()->sentence(3), 0, 32),
            'subject_id' => fake()->numberBetween(1, 100),
            'strategy' => mb_substr(fake()->word(), 0, 32),
            'created_at' => now(),
        ];
    }
}
