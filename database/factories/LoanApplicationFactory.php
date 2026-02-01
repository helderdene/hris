<?php

namespace Database\Factories;

use App\Enums\LoanApplicationStatus;
use App\Enums\LoanType;
use App\Models\Employee;
use App\Models\LoanApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoanApplication>
 */
class LoanApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = LoanApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'reference_number' => null,
            'loan_type' => fake()->randomElement(LoanType::cases()),
            'amount_requested' => fake()->randomFloat(2, 5000, 100000),
            'term_months' => fake()->numberBetween(6, 36),
            'purpose' => fake()->sentence(10),
            'documents' => null,
            'status' => LoanApplicationStatus::Draft,
            'submitted_at' => null,
            'reviewer_employee_id' => null,
            'reviewer_remarks' => null,
            'reviewed_at' => null,
            'employee_loan_id' => null,
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
            'status' => LoanApplicationStatus::Draft,
        ]);
    }

    /**
     * Set as pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanApplicationStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set as approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanApplicationStatus::Approved,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Set as rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanApplicationStatus::Rejected,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => now(),
            'reviewer_remarks' => fake()->sentence(),
        ]);
    }

    /**
     * Set as cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanApplicationStatus::Cancelled,
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Create an application for a specific employee.
     */
    public function forEmployee(Employee|int $employee): static
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId,
        ]);
    }

    /**
     * Create an application with a specific loan type.
     */
    public function ofType(LoanType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_type' => $type,
        ]);
    }
}
