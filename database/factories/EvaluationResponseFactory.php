<?php

namespace Database\Factories;

use App\Models\EvaluationResponse;
use App\Models\EvaluationReviewer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EvaluationResponse>
 */
class EvaluationResponseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EvaluationResponse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_reviewer_id' => EvaluationReviewer::factory(),
            'strengths' => fake()->paragraph(),
            'areas_for_improvement' => fake()->paragraph(),
            'overall_comments' => fake()->paragraph(),
            'development_suggestions' => fake()->paragraph(),
            'is_draft' => true,
            'last_saved_at' => now(),
            'submitted_at' => null,
        ];
    }

    /**
     * Indicate the response is submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => false,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate the response is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => true,
            'submitted_at' => null,
        ]);
    }

    /**
     * Indicate the response has no narrative feedback.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'strengths' => null,
            'areas_for_improvement' => null,
            'overall_comments' => null,
            'development_suggestions' => null,
        ]);
    }
}
