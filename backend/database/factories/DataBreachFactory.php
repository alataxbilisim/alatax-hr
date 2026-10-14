<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DataBreach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataBreach>
 */
class DataBreachFactory extends Factory
{
    protected $model = DataBreach::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'detected_at' => now(),
            'description' => fake()->sentence(),
        ];
    }
}
