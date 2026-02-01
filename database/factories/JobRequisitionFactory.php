<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\JobRequisitionStatus;
use App\Enums\JobRequisitionUrgency;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobRequisition>
 */
class JobRequisitionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = JobRequisition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'department_id' => Department::factory(),
            'requested_by_employee_id' => Employee::factory(),
            'reference_number' => JobRequisition::generateReferenceNumber(),
            'headcount' => fake()->numberBetween(1, 5),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'salary_range_min' => fake()->numberBetween(20000, 40000),
            'salary_range_max' => fake()->numberBetween(40000, 80000),
            'justification' => fake()->paragraph(),
            'urgency' => JobRequisitionUrgency::Normal,
            'preferred_start_date' => fake()->dateTimeBetween('+2 weeks', '+3 months'),
            'requirements' => null,
            'remarks' => null,
            'status' => JobRequisitionStatus::Draft,
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
            'status' => JobRequisitionStatus::Draft,
            'current_approval_level' => 0,
        ]);
    }

    /**
     * Set as pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobRequisitionStatus::Pending,
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
            'status' => JobRequisitionStatus::Approved,
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
            'status' => JobRequisitionStatus::Rejected,
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
            'status' => JobRequisitionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set urgency to urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'urgency' => JobRequisitionUrgency::Urgent,
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
