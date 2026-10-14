<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DestructionApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestructionApproval>
 */
class DestructionApprovalFactory extends Factory
{
    protected $model = DestructionApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'approved_by' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'approved_at' => now(),
            'candidate_ids' => [],
        ];
    }
}
