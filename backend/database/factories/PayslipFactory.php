<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->state(['company_id' => $attributes['company_id']]),
            'period' => fake()->unique()->bothify('????-#####'),
            'year' => (int) now()->year,
            'month' => fake()->numberBetween(1, 12),
        ];
    }
}
