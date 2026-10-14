<?php

namespace Database\Factories;

use App\Models\PerformanceCriteria;
use App\Models\PerformanceReview;
use App\Models\PerformanceScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceScore>
 */
class PerformanceScoreFactory extends Factory
{
    protected $model = PerformanceScore::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => PerformanceReview::factory(),
            'criteria_id' => fn (array $attributes) => PerformanceCriteria::factory()->state(['company_id' => PerformanceReview::withoutGlobalScopes()->whereKey($attributes['review_id'])->value('company_id')]),
            'score' => fake()->numberBetween(1, 100),
        ];
    }
}
