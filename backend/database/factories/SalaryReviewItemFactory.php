<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryReviewItem;
use App\Models\SalaryReviewPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryReviewItem>
 */
class SalaryReviewItemFactory extends Factory
{
    protected $model = SalaryReviewItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'period_id' => fn (array $attributes) => SalaryReviewPeriod::factory()->state(['company_id' => $attributes['company_id']]),
            'employee_id' => fn (array $attributes) => Employee::factory()->state(['company_id' => $attributes['company_id']]),
            'proposed_amount' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
