<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\OneOnOneMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OneOnOneMeeting>
 */
class OneOnOneMeetingFactory extends Factory
{
    protected $model = OneOnOneMeeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'manager_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'employee_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'scheduled_at' => now(),
        ];
    }
}
