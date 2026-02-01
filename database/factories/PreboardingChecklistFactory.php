<?php

namespace Database\Factories;

use App\Enums\PreboardingStatus;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\PreboardingChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreboardingChecklist>
 */
class PreboardingChecklistFactory extends Factory
{
    protected $model = PreboardingChecklist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'offer_id' => Offer::factory(),
            'status' => PreboardingStatus::Pending,
            'deadline' => now()->addDays(14)->toDateString(),
            'completed_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Set the checklist status.
     */
    public function withStatus(PreboardingStatus $status): static
    {
        $data = ['status' => $status];

        if ($status === PreboardingStatus::Completed) {
            $data['completed_at'] = now();
        }

        return $this->state(fn () => $data);
    }
}
