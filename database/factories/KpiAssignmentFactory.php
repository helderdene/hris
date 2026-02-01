<?php

namespace Database\Factories;

use App\Enums\KpiAssignmentStatus;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KpiAssignment>
 */
class KpiAssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = KpiAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kpi_template_id' => KpiTemplate::factory(),
            'performance_cycle_participant_id' => PerformanceCycleParticipant::factory(),
            'target_value' => fake()->randomFloat(2, 100, 10000),
            'weight' => fake()->randomFloat(2, 0.5, 2.0),
            'actual_value' => null,
            'achievement_percentage' => null,
            'status' => KpiAssignmentStatus::Pending,
            'notes' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the assignment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KpiAssignmentStatus::Pending,
            'actual_value' => null,
            'achievement_percentage' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is in progress.
     */
    public function inProgress(): static
    {
        $target = fake()->randomFloat(2, 100, 10000);
        $actual = fake()->randomFloat(2, 0, $target);
        $achievement = $target > 0 ? ($actual / $target) * 100 : 0;

        return $this->state(fn (array $attributes) => [
            'status' => KpiAssignmentStatus::InProgress,
            'target_value' => $target,
            'actual_value' => $actual,
            'achievement_percentage' => $achievement,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is completed.
     */
    public function completed(): static
    {
        $target = fake()->randomFloat(2, 100, 10000);
        $actual = fake()->randomFloat(2, $target * 0.5, $target * 1.2);
        $achievement = $target > 0 ? min(($actual / $target) * 100, 200) : 0;

        return $this->state(fn (array $attributes) => [
            'status' => KpiAssignmentStatus::Completed,
            'target_value' => $target,
            'actual_value' => $actual,
            'achievement_percentage' => $achievement,
            'completed_at' => now(),
        ]);
    }

    /**
     * Set a specific target value.
     */
    public function withTarget(float $target, float $weight = 1.0): static
    {
        return $this->state(fn (array $attributes) => [
            'target_value' => $target,
            'weight' => $weight,
        ]);
    }

    /**
     * Set a specific actual value with calculated achievement.
     */
    public function withProgress(float $actual): static
    {
        return $this->state(function (array $attributes) use ($actual) {
            $target = $attributes['target_value'] ?? 1000;
            $achievement = $target > 0 ? min(($actual / $target) * 100, 200) : 0;

            return [
                'actual_value' => $actual,
                'achievement_percentage' => $achievement,
                'status' => KpiAssignmentStatus::InProgress,
            ];
        });
    }
}
