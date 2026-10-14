<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PerformanceCriteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceCriteria>
 */
class PerformanceCriteriaFactory extends Factory
{
    protected $model = PerformanceCriteria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
        ];
    }
}
