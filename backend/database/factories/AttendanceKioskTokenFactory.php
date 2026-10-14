<?php

namespace Database\Factories;

use App\Models\AttendanceKioskToken;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendanceKioskToken>
 */
class AttendanceKioskTokenFactory extends Factory
{
    protected $model = AttendanceKioskToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'jti' => fake()->unique()->uuid(),
            'token_hash' => Str::random(40),
            'expires_at' => now(),
        ];
    }
}
