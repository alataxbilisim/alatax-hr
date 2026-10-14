<?php

namespace Database\Factories;

use App\Models\EmployeeRequest;
use App\Models\EmployeeRequestHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeRequestHistory>
 */
class EmployeeRequestHistoryFactory extends Factory
{
    protected $model = EmployeeRequestHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_request_id' => EmployeeRequest::factory(),
            'new_status' => fake()->word(),
        ];
    }
}
