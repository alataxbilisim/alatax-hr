<?php

namespace Database\Factories;

use App\Models\TrainingCertificate;
use App\Models\TrainingParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingCertificate>
 */
class TrainingCertificateFactory extends Factory
{
    protected $model = TrainingCertificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_id' => TrainingParticipant::factory(),
            'certificate_number' => strtoupper(fake()->unique()->bothify('??-#####')),
            'issue_date' => fake()->date(),
        ];
    }
}
