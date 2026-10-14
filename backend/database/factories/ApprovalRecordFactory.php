<?php

namespace Database\Factories;

use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRecord>
 */
class ApprovalRecordFactory extends Factory
{
    protected $model = ApprovalRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'approval_workflow_id' => fn (array $attributes) => ApprovalWorkflow::factory()->state(['company_id' => $attributes['company_id']]),
            'approval_step_id' => ApprovalStep::factory(),
            'approvable_type' => fake()->word(),
            'approvable_id' => fake()->numberBetween(1, 100),
            'step_order' => fake()->numberBetween(1, 100),
        ];
    }
}
