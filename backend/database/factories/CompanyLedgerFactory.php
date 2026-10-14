<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyLedger>
 */
class CompanyLedgerFactory extends Factory
{
    protected $model = CompanyLedger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => 'debit',
            'amount' => fake()->randomFloat(2, 0, 1000),
            'balance_after' => fake()->randomFloat(2, 0, 1000),
            'description' => fake()->word(),
        ];
    }
}
