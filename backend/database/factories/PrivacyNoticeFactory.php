<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PrivacyNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivacyNotice>
 */
class PrivacyNoticeFactory extends Factory
{
    protected $model = PrivacyNotice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'audience' => 'employee',
            'version' => fake()->unique()->numberBetween(1, 1000000),
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
        ];
    }
}
