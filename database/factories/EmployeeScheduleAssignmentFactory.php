<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeScheduleAssignment>
 */
class EmployeeScheduleAssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EmployeeScheduleAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_schedule_id' => WorkSchedule::factory(),
            'shift_name' => null,
            'effective_date' => now()->toDateString(),
            'end_date' => null,
        ];
    }

    /**
     * Indicate that the assignment is for a specific shift.
     */
    public function forShift(string $shiftName): static
    {
        return $this->state(fn (array $attributes) => [
            'shift_name' => $shiftName,
        ]);
    }

    /**
     * Indicate that the assignment has ended.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }

    /**
     * Indicate that the assignment is future-dated.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_date' => now()->addMonth()->toDateString(),
            'end_date' => null,
        ]);
    }

    /**
     * Indicate that the assignment is currently active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_date' => now()->toDateString(),
            'end_date' => null,
        ]);
    }

    /**
     * Set a specific date range for the assignment.
     */
    public function forDateRange(string $startDate, ?string $endDate = null): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
