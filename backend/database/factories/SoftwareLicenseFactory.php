<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SoftwareLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoftwareLicense>
 */
class SoftwareLicenseFactory extends Factory
{
    protected $model = SoftwareLicense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'vendor' => fake()->word(),
        ];
    }
}
