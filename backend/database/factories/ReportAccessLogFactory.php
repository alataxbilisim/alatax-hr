<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReportAccessLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportAccessLog>
 */
class ReportAccessLogFactory extends Factory
{
    protected $model = ReportAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['home_company_id' => $attributes['company_id']]),
            'action' => 'run',
        ];
    }
}
