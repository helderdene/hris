<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeLoan>
 */
class EmployeeLoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EmployeeLoan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loanType = fake()->randomElement(LoanType::cases());
        $principalAmount = fake()->randomFloat(2, 5000, 100000);
        $interestRate = fake()->randomFloat(4, 0, 0.12);
        $termMonths = fake()->numberBetween(6, 36);
        $totalAmount = $principalAmount * (1 + ($interestRate * ($termMonths / 12)));
        $monthlyDeduction = $totalAmount / $termMonths;
        $startDate = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'employee_id' => Employee::factory(),
            'loan_type' => $loanType,
            'loan_code' => strtoupper(fake()->unique()->lexify('LOAN-????-????')),
            'reference_number' => fake()->optional(0.7)->numerify('REF-########'),
            'principal_amount' => $principalAmount,
            'interest_rate' => $interestRate,
            'monthly_deduction' => round($monthlyDeduction, 2),
            'term_months' => $termMonths,
            'total_amount' => round($totalAmount, 2),
            'total_paid' => 0,
            'remaining_balance' => round($totalAmount, 2),
            'start_date' => $startDate,
            'expected_end_date' => (clone $startDate)->modify("+{$termMonths} months"),
            'actual_end_date' => null,
            'status' => LoanStatus::Active,
            'notes' => fake()->optional(0.3)->sentence(),
            'metadata' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Create an SSS Salary Loan.
     */
    public function sssSalary(): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_type' => LoanType::SssSalary,
            'interest_rate' => 0.10,
        ]);
    }

    /**
     * Create an SSS Calamity Loan.
     */
    public function sssCalamity(): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_type' => LoanType::SssCalamity,
            'interest_rate' => 0.10,
        ]);
    }

    /**
     * Create a Pag-IBIG MPL (Multi-Purpose Loan).
     */
    public function pagibigMpl(): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_type' => LoanType::PagibigMpl,
            'interest_rate' => 0.105,
        ]);
    }

    /**
     * Create a Pag-IBIG Calamity Loan.
     */
    public function pagibigCalamity(): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_type' => LoanType::PagibigCalamity,
            'interest_rate' => 0.0575,
        ]);
    }

    /**
     * Create a Pag-IBIG Housing Loan.
     */
    public function pagibigHousing(): static
    {
        $termMonths = fake()->numberBetween(120, 360);
        $principalAmount = fake()->randomFloat(2, 500000, 5000000);
        $interestRate = 0.065;
        $totalAmount = $principalAmount * (1 + ($interestRate * ($termMonths / 12)));

        return $this->state(fn (array $attributes) => [
            'loan_type' => LoanType::PagibigHousing,
            'interest_rate' => $attributes['interest_rate'] ?? $interestRate,
            'term_months' => $attributes['term_months'] ?? $termMonths,
            'principal_amount' => $attributes['principal_amount'] ?? $principalAmount,
            'total_amount' => $attributes['total_amount'] ?? round($totalAmount, 2),
            'remaining_balance' => $attributes['remaining_balance'] ?? round($totalAmount, 2),
            'monthly_deduction' => $attributes['monthly_deduction'] ?? round($totalAmount / $termMonths, 2),
        ]);
    }

    /**
     * Create a Company Cash Advance.
     */
    public function companyCashAdvance(): static
    {
        $termMonths = fake()->numberBetween(1, 6);
        $principalAmount = fake()->randomFloat(2, 1000, 20000);

        return $this->state(fn (array $attributes) => [
            'loan_type' => LoanType::CompanyCashAdvance,
            'interest_rate' => $attributes['interest_rate'] ?? 0,
            'term_months' => $attributes['term_months'] ?? $termMonths,
            'principal_amount' => $attributes['principal_amount'] ?? $principalAmount,
            'total_amount' => $attributes['total_amount'] ?? $principalAmount,
            'remaining_balance' => $attributes['remaining_balance'] ?? $principalAmount,
            'monthly_deduction' => $attributes['monthly_deduction'] ?? round($principalAmount / $termMonths, 2),
        ]);
    }

    /**
     * Create a Company Emergency Loan.
     */
    public function companyEmergency(): static
    {
        $termMonths = fake()->numberBetween(3, 12);
        $principalAmount = fake()->randomFloat(2, 5000, 50000);

        return $this->state(fn (array $attributes) => [
            'loan_type' => LoanType::CompanyEmergency,
            'interest_rate' => $attributes['interest_rate'] ?? 0,
            'term_months' => $attributes['term_months'] ?? $termMonths,
            'principal_amount' => $attributes['principal_amount'] ?? $principalAmount,
            'total_amount' => $attributes['total_amount'] ?? $principalAmount,
            'remaining_balance' => $attributes['remaining_balance'] ?? $principalAmount,
            'monthly_deduction' => $attributes['monthly_deduction'] ?? round($principalAmount / $termMonths, 2),
        ]);
    }

    /**
     * Create a loan in active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::Active,
            'actual_end_date' => null,
        ]);
    }

    /**
     * Create a completed loan.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalAmount = $attributes['total_amount'] ?? 50000;

            return [
                'status' => LoanStatus::Completed,
                'total_paid' => $totalAmount,
                'remaining_balance' => 0,
                'actual_end_date' => fake()->dateTimeBetween('-3 months', 'now'),
            ];
        });
    }

    /**
     * Create a loan on hold.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::OnHold,
            'metadata' => [
                'on_hold_at' => now()->subDays(fake()->numberBetween(1, 30))->toDateTimeString(),
                'on_hold_reason' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * Create a cancelled loan.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::Cancelled,
            'metadata' => [
                'cancelled_at' => now()->subDays(fake()->numberBetween(1, 30))->toDateTimeString(),
                'cancellation_reason' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * Create a loan with partial payment.
     */
    public function withPartialPayment(float $percentPaid = 0.5): static
    {
        return $this->state(function (array $attributes) use ($percentPaid) {
            $totalAmount = $attributes['total_amount'] ?? 50000;
            $totalPaid = $totalAmount * $percentPaid;

            return [
                'total_paid' => round($totalPaid, 2),
                'remaining_balance' => round($totalAmount - $totalPaid, 2),
            ];
        });
    }

    /**
     * Create a loan for a specific employee.
     */
    public function forEmployee(Employee|int $employee): static
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId,
        ]);
    }

    /**
     * Create a loan with a specific type.
     */
    public function ofType(LoanType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_type' => $type,
        ]);
    }
}
