<?php

namespace Database\Factories;

use App\Models\ApprovalEscalationAlert;
use App\Models\ApprovalRecord;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalEscalationAlert>
 */
class ApprovalEscalationAlertFactory extends Factory
{
    protected $model = ApprovalEscalationAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'approval_record_id' => fn (array $attributes) => ApprovalRecord::factory()->state(['company_id' => $attributes['company_id']]),
            'alert_level' => mb_substr(fake()->unique()->bothify('????-#####'), 0, 32),
            'notified_at' => now(),
        ];
    }
}
