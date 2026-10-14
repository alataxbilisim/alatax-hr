<?php

namespace Database\Factories;

use App\Models\FeedbackProvider;
use App\Models\FeedbackResponse;
use App\Models\PerformanceCriteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackResponse>
 */
class FeedbackResponseFactory extends Factory
{
    protected $model = FeedbackResponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_criteria_id' => PerformanceCriteria::factory(),
            'feedback_provider_id' => FeedbackProvider::factory(),
        ];
    }
}
