<?php

namespace Database\Factories;

use App\Models\FeedbackProvider;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackProvider>
 */
class FeedbackProviderFactory extends Factory
{
    protected $model = FeedbackProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_review_id' => PerformanceReview::factory(),
            'provider_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => PerformanceReview::withoutGlobalScopes()->whereKey($attributes['performance_review_id'])->value('company_id')]),
            'relationship' => 'self',
        ];
    }
}
