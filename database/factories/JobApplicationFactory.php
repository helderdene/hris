<?php

namespace Database\Factories;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = JobApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'job_posting_id' => JobPosting::factory(),
            'status' => ApplicationStatus::Applied,
            'source' => fake()->randomElement(ApplicationSource::cases()),
            'cover_letter' => fake()->optional(0.6)->paragraphs(2, true),
            'notes' => fake()->optional(0.3)->sentence(),
            'applied_at' => now(),
        ];
    }

    /**
     * Set a specific status with corresponding timestamp.
     */
    public function withStatus(ApplicationStatus $status): static
    {
        $timestamps = ['applied_at' => now()->subDays(10)];

        if (in_array($status, [ApplicationStatus::Screening, ApplicationStatus::Interview, ApplicationStatus::Assessment, ApplicationStatus::Offer, ApplicationStatus::Hired])) {
            $timestamps['screening_at'] = now()->subDays(8);
        }

        if (in_array($status, [ApplicationStatus::Interview, ApplicationStatus::Assessment, ApplicationStatus::Offer, ApplicationStatus::Hired])) {
            $timestamps['interview_at'] = now()->subDays(6);
        }

        if (in_array($status, [ApplicationStatus::Assessment, ApplicationStatus::Offer, ApplicationStatus::Hired])) {
            $timestamps['assessment_at'] = now()->subDays(4);
        }

        if (in_array($status, [ApplicationStatus::Offer, ApplicationStatus::Hired])) {
            $timestamps['offer_at'] = now()->subDays(2);
        }

        if ($status === ApplicationStatus::Hired) {
            $timestamps['hired_at'] = now();
        }

        if ($status === ApplicationStatus::Rejected) {
            $timestamps['rejected_at'] = now();
        }

        if ($status === ApplicationStatus::Withdrawn) {
            $timestamps['withdrawn_at'] = now();
        }

        return $this->state(fn () => array_merge(['status' => $status], $timestamps));
    }
}
