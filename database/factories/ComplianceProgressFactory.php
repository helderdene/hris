<?php

namespace Database\Factories;

use App\Enums\ComplianceProgressStatus;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceModule;
use App\Models\ComplianceProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceProgress>
 */
class ComplianceProgressFactory extends Factory
{
    protected $model = ComplianceProgress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compliance_assignment_id' => ComplianceAssignment::factory(),
            'compliance_module_id' => ComplianceModule::factory(),
            'status' => ComplianceProgressStatus::NotStarted,
            'started_at' => null,
            'completed_at' => null,
            'time_spent_minutes' => 0,
            'progress_percentage' => 0,
            'position_data' => null,
            'best_score' => null,
            'attempts_made' => 0,
            'last_accessed_at' => null,
        ];
    }

    /**
     * Indicate that the progress is not started.
     */
    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceProgressStatus::NotStarted,
            'started_at' => null,
            'completed_at' => null,
            'progress_percentage' => 0,
        ]);
    }

    /**
     * Indicate that the progress is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceProgressStatus::InProgress,
            'started_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'completed_at' => null,
            'progress_percentage' => fake()->numberBetween(10, 90),
            'time_spent_minutes' => fake()->numberBetween(5, 60),
            'last_accessed_at' => fake()->dateTimeBetween('-2 days', 'now'),
        ]);
    }

    /**
     * Indicate that the progress is completed.
     */
    public function completed(): static
    {
        $completedAt = fake()->dateTimeBetween('-7 days', 'now');

        return $this->state(fn (array $attributes) => [
            'status' => ComplianceProgressStatus::Completed,
            'started_at' => fake()->dateTimeBetween('-14 days', $completedAt),
            'completed_at' => $completedAt,
            'progress_percentage' => 100,
            'time_spent_minutes' => fake()->numberBetween(15, 90),
            'last_accessed_at' => $completedAt,
        ]);
    }

    /**
     * Indicate that the progress failed (for assessments).
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceProgressStatus::Failed,
            'completed_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'best_score' => fake()->numberBetween(30, 69),
            'attempts_made' => fake()->numberBetween(1, 3),
        ]);
    }

    /**
     * Set a specific score.
     */
    public function withScore(int $score): static
    {
        return $this->state(fn (array $attributes) => [
            'best_score' => $score,
        ]);
    }
}
