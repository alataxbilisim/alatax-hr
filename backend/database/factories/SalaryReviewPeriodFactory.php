<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalaryReviewPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryReviewPeriod>
 */
class SalaryReviewPeriodFactory extends Factory
{
    protected $model = SalaryReviewPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'effective_date' => fake()->date(),
        ];
    }
}
