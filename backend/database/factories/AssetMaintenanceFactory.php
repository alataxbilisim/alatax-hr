<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetMaintenance>
 */
class AssetMaintenanceFactory extends Factory
{
    protected $model = AssetMaintenance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'title' => fake()->sentence(3),
        ];
    }
}
