<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('POS-###')),
            'name' => $name,
            'department_id' => null,
            'sgk_occupation_code' => fake()->optional()->numerify('####.##'),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 0,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
