<?php

namespace Database\Factories;

use App\Enums\GoalApprovalStatus;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\GoalVisibility;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceCycleInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Goal>
 */
class GoalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Goal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+1 month');
        $dueDate = fake()->dateTimeBetween($startDate, '+6 months');

        return [
            'employee_id' => Employee::factory(),
            'performance_cycle_instance_id' => null,
            'parent_goal_id' => null,
            'goal_type' => fake()->randomElement(GoalType::cases()),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Professional Development', 'Performance', 'Leadership', 'Innovation']),
            'visibility' => GoalVisibility::Private,
            'priority' => GoalPriority::Medium,
            'status' => GoalStatus::Draft,
            'approval_status' => GoalApprovalStatus::NotRequired,
            'approved_by' => null,
            'approved_at' => null,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'completed_at' => null,
            'progress_percentage' => 0,
            'weight' => 1.00,
            'final_score' => null,
            'owner_notes' => null,
            'manager_feedback' => null,
        ];
    }

    /**
     * Indicate that the goal is an OKR objective.
     */
    public function okr(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_type' => GoalType::OkrObjective,
        ]);
    }

    /**
     * Indicate that the goal is a SMART goal.
     */
    public function smart(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_type' => GoalType::SmartGoal,
        ]);
    }

    /**
     * Indicate that the goal is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GoalStatus::Draft,
            'approval_status' => GoalApprovalStatus::NotRequired,
        ]);
    }

    /**
     * Indicate that the goal is pending approval.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GoalStatus::PendingApproval,
            'approval_status' => GoalApprovalStatus::Pending,
        ]);
    }

    /**
     * Indicate that the goal is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GoalStatus::Active,
            'approval_status' => GoalApprovalStatus::Approved,
        ]);
    }

    /**
     * Indicate that the goal is completed.
     */
    public function completed(): static
    {
        $progress = fake()->randomFloat(2, 50, 100);

        return $this->state(fn (array $attributes) => [
            'status' => GoalStatus::Completed,
            'approval_status' => GoalApprovalStatus::Approved,
            'completed_at' => now(),
            'progress_percentage' => $progress,
            'final_score' => $progress,
        ]);
    }

    /**
     * Indicate that the goal is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GoalStatus::Cancelled,
        ]);
    }

    /**
     * Set a specific priority.
     */
    public function withPriority(GoalPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    /**
     * Set visibility to team.
     */
    public function teamVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => GoalVisibility::Team,
        ]);
    }

    /**
     * Set visibility to organization.
     */
    public function organizationVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => GoalVisibility::Organization,
        ]);
    }

    /**
     * Link to a performance cycle instance.
     */
    public function forCycleInstance(PerformanceCycleInstance $instance): static
    {
        return $this->state(fn (array $attributes) => [
            'performance_cycle_instance_id' => $instance->id,
        ]);
    }

    /**
     * Set a parent goal for alignment.
     */
    public function alignedTo(Goal $parentGoal): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_goal_id' => $parentGoal->id,
        ]);
    }

    /**
     * Set specific progress percentage.
     */
    public function withProgress(float $progress): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percentage' => $progress,
            'status' => GoalStatus::Active,
        ]);
    }
}
