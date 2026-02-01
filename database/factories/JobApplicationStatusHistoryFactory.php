<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobApplicationStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobApplicationStatusHistory>
 */
class JobApplicationStatusHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = JobApplicationStatusHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'from_status' => ApplicationStatus::Applied,
            'to_status' => ApplicationStatus::Screening,
            'notes' => fake()->optional(0.5)->sentence(),
            'changed_by' => null,
        ];
    }
}
