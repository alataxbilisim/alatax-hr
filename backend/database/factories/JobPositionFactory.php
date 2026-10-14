<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosition>
 */
class JobPositionFactory extends Factory
{
    protected $model = JobPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(2),
        ];
    }
}
