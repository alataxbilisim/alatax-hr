<?php

namespace Database\Factories;

use App\Models\SurveyQuestion;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyQuestionFactory extends Factory
{
    protected $model = SurveyQuestion::class;

    public function definition(): array
    {
        $types = ['text', 'rating', 'single_choice', 'multiple_choice', 'nps'];
        $type = $this->faker->randomElement($types);

        $options = [];
        if (in_array($type, ['single_choice', 'multiple_choice'])) {
            $options = $this->faker->words(4);
        }

        return [
            'survey_id' => Survey::factory(),
            'question_text' => $this->faker->sentence() . '?',
            'question_type' => $type,
            'is_required' => $this->faker->boolean(80),
            'options' => $options,
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'text',
            'options' => [],
        ]);
    }

    public function rating(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'rating',
            'options' => [],
        ]);
    }

    public function choice(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'single_choice',
            'options' => ['Seçenek A', 'Seçenek B', 'Seçenek C', 'Seçenek D'],
        ]);
    }
}

