<?php

namespace Database\Factories;

use App\Models\RoleDefaultDashboard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoleDefaultDashboard>
 */
class RoleDefaultDashboardFactory extends Factory
{
    protected $model = RoleDefaultDashboard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_key' => Str::random(40),
            'dashboard_system_key' => Str::random(40),
        ];
    }
}
