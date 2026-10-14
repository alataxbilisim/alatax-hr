<?php

namespace Database\Factories;

use App\Models\ApplicationStatusLog;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationStatusLog>
 */
class ApplicationStatusLogFactory extends Factory
{
    protected $model = ApplicationStatusLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'to_status' => fake()->word(),
        ];
    }
}
