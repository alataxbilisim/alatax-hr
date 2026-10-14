<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ConsentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentRecord>
 */
class ConsentRecordFactory extends Factory
{
    protected $model = ConsentRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject_type' => 'employee',
            'subject_id' => fake()->numberBetween(1, 100),
            'consent_type' => 'aydinlatma_okundu',
            'granted' => fake()->boolean(),
            'source' => 'portal',
        ];
    }
}
