<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ExpenseClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseClaimFactory extends Factory
{
    protected $model = ExpenseClaim::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'claim_number' => 'EXP-'.now()->format('Y').'-'.str_pad($this->faker->unique()->randomNumber(4), 4, '0', STR_PAD_LEFT),
            'expense_date' => $this->faker->date(),
            'total_amount' => $this->faker->randomFloat(2, 50, 2000),
            'currency' => 'TRY',
            'status' => 'draft',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => 'bank_transfer',
        ]);
    }
}
