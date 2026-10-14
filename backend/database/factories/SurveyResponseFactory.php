<?php

namespace Database\Factories;

use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_submission_id' => SurveySubmission::factory(),
            'survey_question_id' => SurveyQuestion::factory(),
        ];
    }
}
