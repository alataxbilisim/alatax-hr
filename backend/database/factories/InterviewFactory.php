<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'job_application_id' => fn (array $attributes) => JobApplication::factory()->state(['company_id' => $attributes['company_id']]),
            'job_position_id' => fn (array $attributes) => JobPosition::factory()->state(['company_id' => $attributes['company_id']]),
            'title' => fake()->sentence(3),
            'scheduled_at' => now(),
            'interviewer_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
        ];
    }
}
