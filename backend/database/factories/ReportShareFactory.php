<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReportShare;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportShare>
 */
class ReportShareFactory extends Factory
{
    protected $model = ReportShare::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'saved_report_id' => fn (array $attributes) => SavedReport::factory()->state(['company_id' => $attributes['company_id']]),
            // report_shares_target_check: user/role/department'tan tam biri dolu.
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
        ];
    }
}
