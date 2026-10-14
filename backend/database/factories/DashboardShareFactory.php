<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Dashboard;
use App\Models\DashboardShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardShare>
 */
class DashboardShareFactory extends Factory
{
    protected $model = DashboardShare::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'dashboard_id' => fn (array $attributes) => Dashboard::factory()->state(['company_id' => $attributes['company_id']]),
            // dashboard_shares_target_check: user/role/department'tan tam biri dolu.
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
        ];
    }
}
