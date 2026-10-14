<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PerformancePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformancePeriod>
 */
class PerformancePeriodFactory extends Factory
{
    protected $model = PerformancePeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'start_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
        ];
    }
}
