<?php

namespace Database\Factories;

use App\Enums\SettingScopeType;
use App\Models\Company;
use App\Models\SettingValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SettingValue>
 */
class SettingValueFactory extends Factory
{
    protected $model = SettingValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'scope_type' => SettingScopeType::cases()[0],
            'key' => Str::random(40),
            'value' => [],
        ];
    }
}
