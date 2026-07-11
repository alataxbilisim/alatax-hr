<?php

namespace Database\Factories;

use App\Enums\CompanyPackageType;
use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'legal_name' => $name.' A.Ş.',
            'tax_office' => fake()->city().' VD',
            'tax_number' => fake()->numerify('##########'),
            'phone' => fake()->numerify('05#########'),
            'email' => fake()->unique()->companyEmail(),
            'website' => fake()->optional()->url(),
            'address' => fake()->optional()->streetAddress(),
            'city' => fake()->city(),
            'district' => fake()->optional()->citySuffix(),
            'postal_code' => fake()->optional()->postcode(),
            'country' => 'Türkiye',
            'sector' => fake()->optional()->word(),
            'employee_count' => (string) fake()->numberBetween(10, 500),
            'settings' => null,
            'package_type' => CompanyPackageType::Starter,
            'user_limit' => 50,
            'storage_limit' => 1073741824,
            'license_start_date' => now()->toDateString(),
            'license_end_date' => now()->addYear()->toDateString(),
            'status' => CompanyStatus::Active,
            'trial_ends_at' => null,
            'location_count' => 1,
            'location_limit' => 5,
            'employee_limit' => 100,
            'current_balance' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CompanyStatus::Active,
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CompanyStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CompanyStatus::Suspended,
        ]);
    }
}
