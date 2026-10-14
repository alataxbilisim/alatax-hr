<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    protected $model = AssetAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => Asset::withoutGlobalScopes()->whereKey($attributes['asset_id'])->value('company_id')]),
            'assigned_date' => fake()->date(),
        ];
    }
}
