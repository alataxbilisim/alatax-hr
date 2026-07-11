<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
            'title' => fake()->optional()->jobTitle(),
            'position' => fake()->optional()->jobTitle(),
            'hire_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'contract_type' => 'permanent',
            'work_type' => 'full_time',
            'currency' => 'TRY',
            'status' => 'active',
        ];
    }

    /**
     * Kullanıcı hesabına bağlı aktif personel.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'terminated',
            'termination_date' => now()->toDateString(),
        ]);
    }
}
