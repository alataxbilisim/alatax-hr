<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReportFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportFolder>
 */
class ReportFolderFactory extends Factory
{
    protected $model = ReportFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
        ];
    }
}
