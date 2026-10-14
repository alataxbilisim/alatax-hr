<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentExpiryAlert;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentExpiryAlert>
 */
class DocumentExpiryAlertFactory extends Factory
{
    protected $model = DocumentExpiryAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_document_id' => fn (array $attributes) => EmployeeDocument::factory()->state(['company_id' => $attributes['company_id']]),
            'threshold_days' => fake()->randomElement([7, 15, 30, 60]),
            'notified_at' => now(),
        ];
    }
}
