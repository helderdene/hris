<?php

namespace Database\Factories;

use App\Models\EvaluationSummary;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EvaluationSummary>
 */
class EvaluationSummaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EvaluationSummary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_cycle_participant_id' => PerformanceCycleParticipant::factory(),
            'self_competency_avg' => fake()->randomFloat(2, 2, 5),
            'manager_competency_avg' => fake()->randomFloat(2, 2, 5),
            'peer_competency_avg' => fake()->randomFloat(2, 2, 5),
            'direct_report_competency_avg' => null,
            'overall_competency_avg' => fake()->randomFloat(2, 2, 5),
            'kpi_achievement_score' => fake()->randomFloat(2, 50, 120),
            'manager_kpi_rating' => fake()->numberBetween(1, 5),
            'final_competency_score' => null,
            'final_kpi_score' => null,
            'final_overall_score' => null,
            'final_rating' => null,
            'calibrated_at' => null,
            'calibrated_by' => null,
            'calibration_notes' => null,
            'employee_acknowledged_at' => null,
            'employee_comments' => null,
        ];
    }

    /**
     * Indicate the summary has been calibrated.
     */
    public function calibrated(): static
    {
        return $this->state(fn (array $attributes) => [
            'final_competency_score' => fake()->randomFloat(2, 2, 5),
            'final_kpi_score' => fake()->randomFloat(2, 50, 120),
            'final_overall_score' => fake()->randomFloat(2, 2, 5),
            'final_rating' => fake()->randomElement(['exceptional', 'exceeds_expectations', 'meets_expectations', 'below_expectations', 'needs_improvement']),
            'calibrated_at' => now(),
            'calibration_notes' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate the employee has acknowledged.
     */
    public function acknowledged(): static
    {
        return $this->calibrated()->state(fn (array $attributes) => [
            'employee_acknowledged_at' => now(),
            'employee_comments' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate the summary has no scores yet.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'self_competency_avg' => null,
            'manager_competency_avg' => null,
            'peer_competency_avg' => null,
            'direct_report_competency_avg' => null,
            'overall_competency_avg' => null,
            'kpi_achievement_score' => null,
            'manager_kpi_rating' => null,
        ]);
    }
}
