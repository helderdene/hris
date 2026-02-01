<?php

namespace Database\Factories;

use App\Enums\ComplianceAssignmentStatus;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceAssignmentRule;
use App\Models\ComplianceCourse;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceAssignment>
 */
class ComplianceAssignmentFactory extends Factory
{
    protected $model = ComplianceAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignedDate = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'compliance_course_id' => ComplianceCourse::factory(),
            'employee_id' => Employee::factory(),
            'assignment_rule_id' => null,
            'status' => ComplianceAssignmentStatus::Pending,
            'assigned_date' => $assignedDate,
            'due_date' => fake()->dateTimeBetween($assignedDate, '+60 days'),
            'started_at' => null,
            'completed_at' => null,
            'final_score' => null,
            'attempts_used' => 0,
            'total_time_minutes' => 0,
            'valid_until' => null,
            'exemption_reason' => null,
            'exempted_by' => null,
            'exempted_at' => null,
            'assigned_by' => null,
            'acknowledgment_completed' => false,
            'acknowledged_at' => null,
        ];
    }

    /**
     * Indicate that the assignment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceAssignmentStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceAssignmentStatus::InProgress,
            'started_at' => fake()->dateTimeBetween($attributes['assigned_date'] ?? '-7 days', 'now'),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is completed.
     */
    public function completed(): static
    {
        $completedAt = fake()->dateTimeBetween('-7 days', 'now');

        return $this->state(fn (array $attributes) => [
            'status' => ComplianceAssignmentStatus::Completed,
            'started_at' => fake()->dateTimeBetween($attributes['assigned_date'] ?? '-14 days', $completedAt),
            'completed_at' => $completedAt,
            'final_score' => fake()->numberBetween(70, 100),
            'total_time_minutes' => fake()->numberBetween(30, 180),
            'valid_until' => fake()->optional()->dateTimeBetween('+6 months', '+24 months'),
        ]);
    }

    /**
     * Indicate that the assignment is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceAssignmentStatus::Overdue,
            'due_date' => fake()->dateTimeBetween('-30 days', '-1 day'),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceAssignmentStatus::Expired,
            'completed_at' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'valid_until' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Indicate that the assignment is exempted.
     */
    public function exempted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceAssignmentStatus::Exempted,
            'exemption_reason' => fake()->sentence(),
            'exempted_at' => now(),
        ]);
    }

    /**
     * Set a specific due date.
     */
    public function dueIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->addDays($days),
        ]);
    }

    /**
     * Indicate that the assignment was created by a rule.
     */
    public function fromRule(?ComplianceAssignmentRule $rule = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_rule_id' => $rule?->id ?? ComplianceAssignmentRule::factory(),
        ]);
    }
}
