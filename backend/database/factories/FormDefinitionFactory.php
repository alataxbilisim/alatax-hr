<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FormDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormDefinition>
 */
class FormDefinitionFactory extends Factory
{
    protected $model = FormDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'entity_type' => fake()->unique()->bothify('????-#####'),
            'name' => ucfirst(fake()->words(2, true)),
            'layout' => [],
        ];
    }
}
