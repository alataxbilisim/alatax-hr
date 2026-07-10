<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        $categories = ['Yemek', 'Ulaşım', 'Konaklama', 'Kırtasiye', 'Teknoloji', 'Eğitim'];

        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->randomElement($categories),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'description' => $this->faker->sentence(),
            'max_amount' => $this->faker->optional()->randomFloat(2, 100, 5000),
            'requires_receipt' => true,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

