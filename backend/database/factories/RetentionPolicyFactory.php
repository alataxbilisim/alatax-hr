<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\RetentionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetentionPolicy>
 */
class RetentionPolicyFactory extends Factory
{
    protected $model = RetentionPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'data_category' => fake()->word(),
            'subject_type' => mb_substr(fake()->sentence(3), 0, 32),
            'trigger_event' => 'ise_giris',
            'retention_months' => fake()->numberBetween(1, 100),
            'name' => ucfirst(fake()->words(2, true)),
        ];
    }
}
