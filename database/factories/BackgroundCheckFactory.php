<?php

namespace Database\Factories;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackgroundCheck>
 */
class BackgroundCheckFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = BackgroundCheck::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'check_type' => fake()->randomElement(['Criminal', 'Employment', 'Education', 'Credit', 'Identity']),
            'status' => BackgroundCheckStatus::Pending,
            'provider' => fake()->optional(0.5)->company(),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Set a specific status.
     */
    public function withStatus(BackgroundCheckStatus $status): static
    {
        $data = ['status' => $status];

        if (in_array($status, [BackgroundCheckStatus::InProgress, BackgroundCheckStatus::Passed, BackgroundCheckStatus::Failed], true)) {
            $data['started_at'] = now()->subDays(3);
        }

        if (in_array($status, [BackgroundCheckStatus::Passed, BackgroundCheckStatus::Failed], true)) {
            $data['completed_at'] = now();
        }

        return $this->state(fn () => $data);
    }
}
