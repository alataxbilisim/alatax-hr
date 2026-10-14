<?php

namespace Database\Factories;

use App\Models\Survey;
use App\Models\SurveySubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveySubmission>
 */
class SurveySubmissionFactory extends Factory
{
    protected $model = SurveySubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'started_at' => now(),
        ];
    }
}
