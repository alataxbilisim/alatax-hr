<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryRecord>
 */
class SalaryRecordFactory extends Factory
{
    protected $model = SalaryRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->state(['company_id' => $attributes['company_id']]),
            'effective_date' => fake()->date(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'change_reason' => fake()->word(),
        ];
    }
}
