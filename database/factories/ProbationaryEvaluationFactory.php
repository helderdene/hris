<?php

namespace Database\Factories;

use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\ProbationaryMilestone;
use App\Enums\RegularizationRecommendation;
use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProbationaryEvaluation>
 */
class ProbationaryEvaluationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProbationaryEvaluation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $milestoneDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $dueDate = (clone $milestoneDate)->modify('+14 days');

        return [
            'employee_id' => Employee::factory(),
            'evaluator_id' => Employee::factory(),
            'evaluator_name' => fake()->name(),
            'evaluator_position' => fake()->jobTitle(),
            'milestone' => ProbationaryMilestone::ThirdMonth,
            'milestone_date' => $milestoneDate,
            'due_date' => $dueDate,
            'previous_evaluation_id' => null,
            'status' => ProbationaryEvaluationStatus::Pending,
            'criteria_ratings' => null,
            'overall_rating' => null,
            'strengths' => null,
            'areas_for_improvement' => null,
            'manager_comments' => null,
            'recommendation' => null,
            'recommendation_conditions' => null,
            'extension_months' => null,
            'recommendation_reason' => null,
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }

    /**
     * Set as third month evaluation.
     */
    public function thirdMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'milestone' => ProbationaryMilestone::ThirdMonth,
        ]);
    }

    /**
     * Set as fifth month evaluation.
     */
    public function fifthMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'milestone' => ProbationaryMilestone::FifthMonth,
        ]);
    }

    /**
     * Set as pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProbationaryEvaluationStatus::Pending,
        ]);
    }

    /**
     * Set as draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProbationaryEvaluationStatus::Draft,
        ]);
    }

    /**
     * Set as submitted status.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProbationaryEvaluationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set as HR review status.
     */
    public function hrReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProbationaryEvaluationStatus::HrReview,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set as approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProbationaryEvaluationStatus::Approved,
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Set as rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProbationaryEvaluationStatus::Rejected,
            'submitted_at' => now()->subDay(),
        ]);
    }

    /**
     * Set as revision requested status.
     */
    public function revisionRequested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProbationaryEvaluationStatus::RevisionRequested,
            'submitted_at' => now()->subDay(),
        ]);
    }

    /**
     * Set with filled criteria ratings.
     */
    public function withRatings(): static
    {
        return $this->state(fn (array $attributes) => [
            'criteria_ratings' => [
                ['criteria_id' => 1, 'name' => 'Work Quality', 'weight' => 1, 'rating' => fake()->numberBetween(3, 5), 'comments' => null],
                ['criteria_id' => 2, 'name' => 'Reliability', 'weight' => 1, 'rating' => fake()->numberBetween(3, 5), 'comments' => null],
                ['criteria_id' => 3, 'name' => 'Teamwork', 'weight' => 1, 'rating' => fake()->numberBetween(3, 5), 'comments' => null],
            ],
            'overall_rating' => fake()->randomFloat(2, 3, 5),
            'strengths' => fake()->paragraph(),
            'areas_for_improvement' => fake()->paragraph(),
        ]);
    }

    /**
     * Set with regularization recommendation.
     */
    public function withRecommendation(RegularizationRecommendation $recommendation = RegularizationRecommendation::Recommend): static
    {
        $state = [
            'recommendation' => $recommendation,
        ];

        if ($recommendation->requiresConditions()) {
            $state['recommendation_conditions'] = fake()->paragraph();
        }
        if ($recommendation->requiresExtensionMonths()) {
            $state['extension_months'] = fake()->numberBetween(1, 3);
        }
        if ($recommendation->requiresReason()) {
            $state['recommendation_reason'] = fake()->paragraph();
        }

        return $this->state(fn (array $attributes) => $state);
    }

    /**
     * Set a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Set a specific evaluator.
     */
    public function forEvaluator(Employee $evaluator): static
    {
        return $this->state(fn (array $attributes) => [
            'evaluator_id' => $evaluator->id,
            'evaluator_name' => $evaluator->full_name,
            'evaluator_position' => $evaluator->position?->name,
        ]);
    }

    /**
     * Link to previous evaluation.
     */
    public function withPreviousEvaluation(ProbationaryEvaluation $previous): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_evaluation_id' => $previous->id,
        ]);
    }
}
