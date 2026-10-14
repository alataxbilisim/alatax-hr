<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->state(['company_id' => $attributes['company_id']]),
            'title' => fake()->sentence(3),
            'category' => fake()->word(),
            'file_path' => 'factory/'.fake()->uuid().'.pdf',
            'file_name' => 'factory/'.fake()->uuid().'.pdf',
        ];
    }
}
