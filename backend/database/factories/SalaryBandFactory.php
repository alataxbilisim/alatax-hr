<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Position;
use App\Models\SalaryBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryBand>
 */
class SalaryBandFactory extends Factory
{
    protected $model = SalaryBand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'position_id' => fn (array $attributes) => Position::factory()->state(['company_id' => $attributes['company_id']]),
            'min_amount' => 1000,
            'mid_amount' => 2000,
            'max_amount' => 3000,
        ];
    }
}
