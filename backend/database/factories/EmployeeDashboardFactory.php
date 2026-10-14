<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmployeeDashboard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDashboard>
 */
class EmployeeDashboardFactory extends Factory
{
    protected $model = EmployeeDashboard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'name' => ucfirst(fake()->words(2, true)),
            'widgets' => [],
        ];
    }
}
