<?php

namespace Database\Factories;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalStep>
 */
class ApprovalStepFactory extends Factory
{
    protected $model = ApprovalStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_workflow_id' => ApprovalWorkflow::factory(),
            'step_order' => fake()->numberBetween(1, 100),
            'name' => ucfirst(fake()->words(2, true)),
            'approver_type' => 'direct_manager',
        ];
    }
}
