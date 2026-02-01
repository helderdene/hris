<?php

namespace Database\Factories;

use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoanPayment>
 */
class LoanPaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = LoanPayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $balanceBefore = fake()->randomFloat(2, 10000, 100000);
        $amount = fake()->randomFloat(2, 500, min(5000, $balanceBefore));
        $balanceAfter = max(0, $balanceBefore - $amount);

        return [
            'employee_loan_id' => EmployeeLoan::factory(),
            'payroll_deduction_id' => null,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'payment_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'payment_source' => 'payroll',
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    /**
     * Create a payroll payment.
     */
    public function fromPayroll(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_source' => 'payroll',
        ]);
    }

    /**
     * Create a manual payment.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_source' => 'manual',
            'payroll_deduction_id' => null,
        ]);
    }

    /**
     * Create an adjustment payment.
     */
    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_source' => 'adjustment',
            'payroll_deduction_id' => null,
            'notes' => 'Balance adjustment: '.fake()->sentence(),
        ]);
    }

    /**
     * Create a payment for a specific loan.
     */
    public function forLoan(EmployeeLoan|int $loan): static
    {
        $loanId = $loan instanceof EmployeeLoan ? $loan->id : $loan;

        return $this->state(fn (array $attributes) => [
            'employee_loan_id' => $loanId,
        ]);
    }

    /**
     * Create a payment with specific amount.
     */
    public function amount(float $amount): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $balanceBefore = $attributes['balance_before'] ?? $amount * 10;
            $balanceAfter = max(0, $balanceBefore - $amount);

            return [
                'amount' => $amount,
                'balance_after' => $balanceAfter,
            ];
        });
    }

    /**
     * Create a final payment that zeroes the balance.
     */
    public function finalPayment(): static
    {
        return $this->state(function (array $attributes) {
            $balanceBefore = $attributes['balance_before'] ?? 5000;

            return [
                'amount' => $balanceBefore,
                'balance_after' => 0,
                'notes' => 'Final payment - loan completed',
            ];
        });
    }
}
