<?php

namespace Database\Factories;

use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Models\Interview;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Interview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'type' => fake()->randomElement(InterviewType::cases()),
            'status' => InterviewStatus::Scheduled,
            'title' => fake()->sentence(3),
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'location' => fake()->optional(0.5)->address(),
            'meeting_url' => fake()->optional(0.5)->url(),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Set a specific status.
     */
    public function withStatus(InterviewStatus $status): static
    {
        $data = ['status' => $status];

        if ($status === InterviewStatus::Cancelled) {
            $data['cancelled_at'] = now();
            $data['cancellation_reason'] = fake()->sentence();
        }

        return $this->state(fn () => $data);
    }

    /**
     * Set as a video interview with a meeting URL.
     */
    public function video(): static
    {
        return $this->state(fn () => [
            'type' => InterviewType::VideoInterview,
            'meeting_url' => fake()->url(),
        ]);
    }
}
