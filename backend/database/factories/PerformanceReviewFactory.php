<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    protected $model = PerformanceReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'period_id' => fn (array $attributes) => PerformancePeriod::factory()->state(['company_id' => $attributes['company_id']]),
            'employee_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'reviewer_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
        ];
    }
}
