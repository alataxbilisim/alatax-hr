<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DataProcessingActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DataProcessingActivity>
 */
class DataProcessingActivityFactory extends Factory
{
    protected $model = DataProcessingActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'key' => Str::random(40),
            'name' => ucfirst(fake()->words(2, true)),
            'legal_basis' => 'explicit_consent',
            'data_subject_group' => 'employee',
        ];
    }
}
