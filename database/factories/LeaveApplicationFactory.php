<?php

namespace Database\Factories;

use App\Enums\LeaveApplicationStatus;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveApplication>
 */
class LeaveApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = LeaveApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+1 month');
        $daysToAdd = fake()->numberBetween(1, 5);
        $endDate = (clone $startDate)->modify("+{$daysToAdd} days");

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'leave_balance_id' => null,
            'reference_number' => LeaveApplication::generateReferenceNumber(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => LeaveApplication::calculateTotalDays(
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ),
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'reason' => fake()->sentence(10),
            'status' => LeaveApplicationStatus::Draft,
            'current_approval_level' => 0,
            'total_approval_levels' => 1,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'metadata' => null,
            'created_by' => null,
        ];
    }

    /**
     * Set as draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveApplicationStatus::Draft,
            'current_approval_level' => 0,
        ]);
    }

    /**
     * Set as pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveApplicationStatus::Pending,
            'current_approval_level' => 1,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set as approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveApplicationStatus::Approved,
            'current_approval_level' => $attributes['total_approval_levels'] ?? 1,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now(),
        ]);
    }

    /**
     * Set as rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveApplicationStatus::Rejected,
            'submitted_at' => now()->subDays(2),
            'rejected_at' => now(),
        ]);
    }

    /**
     * Set as cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveApplicationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set specific dates for the leave.
     */
    public function forDates(string $startDate, string $endDate): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => LeaveApplication::calculateTotalDays($startDate, $endDate),
        ]);
    }

    /**
     * Set total days directly.
     */
    public function forDays(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'total_days' => $days,
        ]);
    }

    /**
     * Set half-day options.
     */
    public function halfDayStart(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_half_day_start' => true,
        ]);
    }

    /**
     * Set half-day options.
     */
    public function halfDayEnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_half_day_end' => true,
        ]);
    }

    /**
     * Link to a specific leave balance.
     */
    public function withBalance(LeaveBalance $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'leave_balance_id' => $balance->id,
            'employee_id' => $balance->employee_id,
            'leave_type_id' => $balance->leave_type_id,
        ]);
    }

    /**
     * Set the number of approval levels.
     */
    public function withApprovalLevels(int $levels): static
    {
        return $this->state(fn (array $attributes) => [
            'total_approval_levels' => $levels,
        ]);
    }
}
