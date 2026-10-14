<?php

namespace Database\Factories;

use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingParticipant>
 */
class TrainingParticipantFactory extends Factory
{
    protected $model = TrainingParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => TrainingSession::factory(),
            'user_id' => User::factory(),
        ];
    }
}
