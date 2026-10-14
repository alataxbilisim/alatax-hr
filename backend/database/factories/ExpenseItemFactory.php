<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseItem>
 */
class ExpenseItemFactory extends Factory
{
    protected $model = ExpenseItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_claim_id' => ExpenseClaim::factory(),
            'expense_category_id' => fn (array $attributes) => ExpenseCategory::factory()->state(['company_id' => ExpenseClaim::withoutGlobalScopes()->whereKey($attributes['expense_claim_id'])->value('company_id')]),
            'description' => fake()->word(),
            'item_date' => fake()->date(),
            'amount' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
