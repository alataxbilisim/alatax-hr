<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\RequestType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeRequest>
 */
class EmployeeRequestFactory extends Factory
{
    protected $model = EmployeeRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->state(['company_id' => $attributes['company_id']]),
            'request_type_id' => fn (array $attributes) => RequestType::factory()->state(['company_id' => $attributes['company_id']]),
            'title' => fake()->sentence(3),
        ];
    }
}
