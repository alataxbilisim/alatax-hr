<?php

namespace Database\Factories;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalInstance>
 */
class ApprovalInstanceFactory extends Factory
{
    protected $model = ApprovalInstance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'approval_workflow_id' => fn (array $attributes) => ApprovalWorkflow::factory()->state(['company_id' => $attributes['company_id']]),
            'approvable_type' => fake()->word(),
            'approvable_id' => fake()->numberBetween(1, 100),
        ];
    }
}
