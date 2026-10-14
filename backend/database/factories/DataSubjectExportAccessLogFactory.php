<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DataSubjectExportAccessLog;
use App\Models\DataSubjectExportPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSubjectExportAccessLog>
 */
class DataSubjectExportAccessLogFactory extends Factory
{
    protected $model = DataSubjectExportAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'export_package_id' => fn (array $attributes) => DataSubjectExportPackage::factory()->state(['company_id' => $attributes['company_id']]),
            'action' => mb_substr(fake()->word(), 0, 32),
        ];
    }
}
