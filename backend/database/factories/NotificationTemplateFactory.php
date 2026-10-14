<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'event_key' => Str::random(40),
            'subject' => fake()->sentence(3),
            'body' => fake()->sentence(),
        ];
    }
}
