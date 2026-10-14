<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReportMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportMeasure>
 */
class ReportMeasureFactory extends Factory
{
    protected $model = ReportMeasure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'dataset_key' => Str::random(40),
            'key' => Str::random(40),
            'label' => fake()->word(),
            'expression' => fake()->sentence(),
        ];
    }
}
