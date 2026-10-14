<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DataSubjectExportPackage;
use App\Models\DataSubjectRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSubjectExportPackage>
 */
class DataSubjectExportPackageFactory extends Factory
{
    protected $model = DataSubjectExportPackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'data_subject_request_id' => fn (array $attributes) => DataSubjectRequest::factory()->state(['company_id' => $attributes['company_id']]),
            'uuid' => fake()->unique()->uuid(),
        ];
    }
}
