<?php

namespace Database\Factories;

use App\Models\CompetencyEvaluation;
use App\Models\PerformanceCycleParticipant;
use App\Models\PositionCompetency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetencyEvaluation>
 */
class CompetencyEvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_cycle_participant_id' => PerformanceCycleParticipant::factory(),
            'position_competency_id' => PositionCompetency::factory(),
            'self_rating' => null,
            'self_comments' => null,
            'manager_rating' => null,
            'manager_comments' => null,
            'final_rating' => null,
            'evidence' => [],
            'evaluated_at' => null,
        ];
    }

    /**
     * Indicate that the evaluation has a self rating.
     */
    public function withSelfRating(?int $rating = null): static
    {
        return $this->state(fn (array $attributes) => [
            'self_rating' => $rating ?? fake()->numberBetween(1, 5),
            'self_comments' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the evaluation has a manager rating.
     */
    public function withManagerRating(?int $rating = null): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_rating' => $rating ?? fake()->numberBetween(1, 5),
            'manager_comments' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the evaluation is complete with a final rating.
     */
    public function completed(?int $finalRating = null): static
    {
        return $this->state(fn (array $attributes) => [
            'self_rating' => fake()->numberBetween(1, 5),
            'self_comments' => fake()->sentence(),
            'manager_rating' => fake()->numberBetween(1, 5),
            'manager_comments' => fake()->sentence(),
            'final_rating' => $finalRating ?? fake()->numberBetween(1, 5),
            'evaluated_at' => now(),
        ]);
    }

    /**
     * Indicate the evaluation belongs to a specific participant.
     */
    public function forParticipant(PerformanceCycleParticipant $participant): static
    {
        return $this->state(fn (array $attributes) => [
            'performance_cycle_participant_id' => $participant->id,
        ]);
    }

    /**
     * Indicate the evaluation is for a specific position competency.
     */
    public function forPositionCompetency(PositionCompetency $positionCompetency): static
    {
        return $this->state(fn (array $attributes) => [
            'position_competency_id' => $positionCompetency->id,
        ]);
    }
}
