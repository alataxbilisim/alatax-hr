<?php

namespace Database\Factories;

use App\Models\LicensePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicensePackage>
 */
class LicensePackageFactory extends Factory
{
    protected $model = LicensePackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(2, true)),
            'slug' => fake()->unique()->slug(2),
        ];
    }
}
