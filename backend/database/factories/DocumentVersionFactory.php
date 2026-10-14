<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version_number' => fake()->unique()->numberBetween(1, 1000000),
            'file_path' => 'factory/'.fake()->uuid().'.pdf',
            'file_name' => 'factory/'.fake()->uuid().'.pdf',
            'uploaded_by' => fn (array $attributes) => User::factory()->state(['home_company_id' => Document::withoutGlobalScopes()->whereKey($attributes['document_id'])->value('company_id')]),
        ];
    }
}
