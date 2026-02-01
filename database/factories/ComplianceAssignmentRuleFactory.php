<?php

namespace Database\Factories;

use App\Enums\ComplianceRuleType;
use App\Models\ComplianceAssignmentRule;
use App\Models\ComplianceCourse;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceAssignmentRule>
 */
class ComplianceAssignmentRuleFactory extends Factory
{
    protected $model = ComplianceAssignmentRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compliance_course_id' => ComplianceCourse::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'rule_type' => fake()->randomElement(ComplianceRuleType::cases()),
            'conditions' => [],
            'days_to_complete_override' => null,
            'priority' => fake()->numberBetween(1, 10),
            'is_active' => true,
            'apply_to_new_hires' => true,
            'apply_to_existing' => true,
            'effective_from' => null,
            'effective_until' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the rule applies to all employees.
     */
    public function allEmployees(): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => ComplianceRuleType::AllEmployees,
            'conditions' => [],
        ]);
    }

    /**
     * Indicate that the rule applies to a specific department.
     */
    public function forDepartment(int $departmentId): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => ComplianceRuleType::Department,
            'conditions' => ['department_ids' => [$departmentId]],
        ]);
    }

    /**
     * Indicate that the rule applies to a specific position.
     */
    public function forPosition(int $positionId): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => ComplianceRuleType::Position,
            'conditions' => ['position_ids' => [$positionId]],
        ]);
    }

    /**
     * Indicate that the rule is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the rule is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the rule applies only to new hires.
     */
    public function newHiresOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'apply_to_new_hires' => true,
            'apply_to_existing' => false,
        ]);
    }

    /**
     * Indicate that the rule applies only to existing employees.
     */
    public function existingOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'apply_to_new_hires' => false,
            'apply_to_existing' => true,
        ]);
    }

    /**
     * Set the effective date range.
     */
    public function effectiveBetween(string $from, string $until): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => $from,
            'effective_until' => $until,
        ]);
    }

    /**
     * Set a specific creator for the rule.
     */
    public function createdBy(?Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $employee?->id,
        ]);
    }
}
