<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'file_name' => 'factory/'.fake()->uuid().'.pdf',
            'file_path' => 'factory/'.fake()->uuid().'.pdf',
        ];
    }
}
