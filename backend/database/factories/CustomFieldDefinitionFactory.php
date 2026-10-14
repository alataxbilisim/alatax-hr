<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'entity_type' => fake()->unique()->bothify('????-#####'),
            'field_key' => Str::random(40),
            'field_label' => fake()->word(),
            'field_type' => fake()->word(),
        ];
    }
}
