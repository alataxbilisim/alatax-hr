<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementRead>
 */
class AnnouncementReadFactory extends Factory
{
    protected $model = AnnouncementRead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->state(['company_id' => Announcement::withoutGlobalScopes()->whereKey($attributes['announcement_id'])->value('company_id')]),
            'read_at' => now(),
        ];
    }
}
