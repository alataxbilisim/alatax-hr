<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SavedReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedReport>
 */
class SavedReportFactory extends Factory
{
    protected $model = SavedReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'config' => [],
        ];
    }
}
