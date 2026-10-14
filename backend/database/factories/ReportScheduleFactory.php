<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReportSchedule;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'report_id' => fn (array $attributes) => SavedReport::factory()->state(['company_id' => $attributes['company_id']]),
            'owner_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'name' => ucfirst(fake()->words(2, true)),
            'cadence' => 'daily',
        ];
    }
}
