<?php

namespace Database\Factories;

use App\Enums\AssignmentMethod;
use App\Enums\EvaluationReviewerStatus;
use App\Enums\ReviewerType;
use App\Models\Employee;
use App\Models\EvaluationReviewer;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EvaluationReviewer>
 */
class EvaluationReviewerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EvaluationReviewer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_cycle_participant_id' => PerformanceCycleParticipant::factory(),
            'reviewer_employee_id' => Employee::factory(),
            'reviewer_type' => ReviewerType::Peer,
            'status' => EvaluationReviewerStatus::Pending,
            'assignment_method' => AssignmentMethod::Automatic,
            'assigned_by' => null,
            'invited_at' => now(),
            'started_at' => null,
            'submitted_at' => null,
            'declined_at' => null,
            'decline_reason' => null,
        ];
    }

    /**
     * Indicate this is a self reviewer.
     */
    public function self(): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewer_type' => ReviewerType::Self,
        ]);
    }

    /**
     * Indicate this is a manager reviewer.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewer_type' => ReviewerType::Manager,
        ]);
    }

    /**
     * Indicate this is a peer reviewer.
     */
    public function peer(): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewer_type' => ReviewerType::Peer,
        ]);
    }

    /**
     * Indicate this is a direct report reviewer.
     */
    public function directReport(): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewer_type' => ReviewerType::DirectReport,
        ]);
    }

    /**
     * Indicate the reviewer has started.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvaluationReviewerStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate the reviewer has submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvaluationReviewerStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate the reviewer has declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvaluationReviewerStatus::Declined,
            'declined_at' => now(),
            'decline_reason' => fake()->sentence(),
        ]);
    }
}
