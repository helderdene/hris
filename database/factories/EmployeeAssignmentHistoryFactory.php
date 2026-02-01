<?php

namespace Database\Factories;

use App\Enums\AssignmentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use App\Models\Position;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeAssignmentHistory>
 */
class EmployeeAssignmentHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EmployeeAssignmentHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'assignment_type' => fake()->randomElement(AssignmentType::cases()),
            'previous_value_id' => null,
            'new_value_id' => fake()->numberBetween(1, 100),
            'effective_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'remarks' => fake()->optional()->sentence(),
            'changed_by' => fake()->optional()->numberBetween(1, 100),
            'ended_at' => null,
        ];
    }

    /**
     * Indicate that this is a past assignment record (ended).
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => fake()->dateTimeBetween($attributes['effective_date'] ?? '-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that this is a current assignment record.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }

    /**
     * Create a position assignment.
     */
    public function position(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_type' => AssignmentType::Position,
            'new_value_id' => Position::factory(),
        ]);
    }

    /**
     * Create a department assignment.
     */
    public function department(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_type' => AssignmentType::Department,
            'new_value_id' => Department::factory(),
        ]);
    }

    /**
     * Create a work location assignment.
     */
    public function location(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_type' => AssignmentType::Location,
            'new_value_id' => WorkLocation::factory(),
        ]);
    }

    /**
     * Create a supervisor assignment.
     */
    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_type' => AssignmentType::Supervisor,
            'new_value_id' => Employee::factory(),
        ]);
    }

    /**
     * Create an initial assignment record (first assignment, no previous value).
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_value_id' => null,
            'remarks' => 'Initial assignment',
        ]);
    }

    /**
     * Create an assignment change (has previous value).
     */
    public function change(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_value_id' => fake()->numberBetween(1, 100),
            'remarks' => fake()->optional()->sentence(),
        ]);
    }
}
