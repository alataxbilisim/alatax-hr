<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lookup>
 */
class LookupFactory extends Factory
{
    protected $model = Lookup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'lookup_type' => fake()->unique()->bothify('????-#####'),
            'value' => fake()->unique()->bothify('????-#####'),
            'label' => fake()->word(),
        ];
    }
}
