<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'leave_type_id' => fn (array $attributes) => LeaveType::factory()->state(['company_id' => $attributes['company_id']]),
            'year' => (int) now()->year,
        ];
    }
}
