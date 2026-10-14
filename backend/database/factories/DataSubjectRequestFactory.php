<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DataSubjectRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSubjectRequest>
 */
class DataSubjectRequestFactory extends Factory
{
    protected $model = DataSubjectRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject_type' => 'employee',
            'applicant_name' => ucfirst(fake()->words(2, true)),
            'contact' => fake()->word(),
            'request_types' => [],
            'channel' => 'portal',
            'due_date' => fake()->date(),
        ];
    }
}
