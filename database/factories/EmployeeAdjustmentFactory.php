<?php

namespace Database\Factories;

use App\Enums\AdjustmentCategory;
use App\Enums\AdjustmentFrequency;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\RecurringInterval;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeAdjustment>
 */
class EmployeeAdjustmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EmployeeAdjustment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $adjustmentType = fake()->randomElement(AdjustmentType::cases());
        $frequency = fake()->randomElement(AdjustmentFrequency::cases());
        $amount = fake()->randomFloat(2, 500, 10000);

        $data = [
            'employee_id' => Employee::factory(),
            'adjustment_category' => $adjustmentType->category(),
            'adjustment_type' => $adjustmentType,
            'adjustment_code' => strtoupper(fake()->unique()->lexify('ADJ-????-????')),
            'name' => $adjustmentType->label(),
            'description' => fake()->optional(0.5)->sentence(),
            'amount' => $amount,
            'is_taxable' => fake()->boolean(70),
            'frequency' => $frequency,
            'has_balance_tracking' => false,
            'total_amount' => null,
            'total_applied' => 0,
            'remaining_balance' => null,
            'target_payroll_period_id' => null,
            'status' => AdjustmentStatus::Active,
            'notes' => fake()->optional(0.3)->sentence(),
            'metadata' => null,
            'created_by' => User::factory(),
        ];

        if ($frequency === AdjustmentFrequency::Recurring) {
            $startDate = fake()->dateTimeBetween('-3 months', 'now');
            $data['recurring_start_date'] = $startDate;
            $data['recurring_end_date'] = fake()->optional(0.5)->dateTimeBetween($startDate, '+1 year');
            $data['recurring_interval'] = fake()->randomElement(RecurringInterval::cases());
            $data['remaining_occurrences'] = fake()->optional(0.3)->numberBetween(1, 24);
        }

        return $data;
    }

    /**
     * Create a one-time adjustment.
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => AdjustmentFrequency::OneTime,
            'recurring_start_date' => null,
            'recurring_end_date' => null,
            'recurring_interval' => null,
            'remaining_occurrences' => null,
        ]);
    }

    /**
     * Create a recurring adjustment.
     */
    public function recurring(): static
    {
        $startDate = fake()->dateTimeBetween('-3 months', 'now');

        return $this->state(fn (array $attributes) => [
            'frequency' => AdjustmentFrequency::Recurring,
            'recurring_start_date' => $startDate,
            'recurring_end_date' => fake()->optional(0.5)->dateTimeBetween($startDate, '+1 year'),
            'recurring_interval' => fake()->randomElement(RecurringInterval::cases()),
        ]);
    }

    /**
     * Create a transportation allowance.
     */
    public function transportationAllowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Earning,
            'adjustment_type' => AdjustmentType::AllowanceTransportation,
            'name' => 'Transportation Allowance',
            'amount' => fake()->randomFloat(2, 500, 3000),
            'is_taxable' => false,
        ]);
    }

    /**
     * Create a meal allowance.
     */
    public function mealAllowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Earning,
            'adjustment_type' => AdjustmentType::AllowanceMeal,
            'name' => 'Meal Allowance',
            'amount' => fake()->randomFloat(2, 1000, 4000),
            'is_taxable' => false,
        ]);
    }

    /**
     * Create a performance bonus.
     */
    public function performanceBonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Earning,
            'adjustment_type' => AdjustmentType::BonusPerformance,
            'name' => 'Performance Bonus',
            'amount' => fake()->randomFloat(2, 5000, 50000),
            'is_taxable' => true,
            'frequency' => AdjustmentFrequency::OneTime,
        ]);
    }

    /**
     * Create a salary advance (loan-type deduction with balance tracking).
     */
    public function salaryAdvance(): static
    {
        $totalAmount = fake()->randomFloat(2, 5000, 50000);
        $monthlyDeduction = fake()->randomFloat(2, 1000, 10000);

        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Deduction,
            'adjustment_type' => AdjustmentType::LoanSalaryAdvance,
            'name' => 'Salary Advance',
            'amount' => $monthlyDeduction,
            'is_taxable' => false,
            'frequency' => AdjustmentFrequency::Recurring,
            'recurring_start_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'recurring_end_date' => null,
            'recurring_interval' => RecurringInterval::EveryPeriod,
            'has_balance_tracking' => true,
            'total_amount' => $totalAmount,
            'total_applied' => 0,
            'remaining_balance' => $totalAmount,
        ]);
    }

    /**
     * Create a company loan (loan-type deduction with balance tracking).
     */
    public function companyLoan(): static
    {
        $totalAmount = fake()->randomFloat(2, 10000, 100000);
        $monthlyDeduction = fake()->randomFloat(2, 2000, 15000);

        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Deduction,
            'adjustment_type' => AdjustmentType::LoanCompanyLoan,
            'name' => 'Company Loan',
            'amount' => $monthlyDeduction,
            'is_taxable' => false,
            'frequency' => AdjustmentFrequency::Recurring,
            'recurring_start_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'recurring_end_date' => null,
            'recurring_interval' => RecurringInterval::EveryPeriod,
            'has_balance_tracking' => true,
            'total_amount' => $totalAmount,
            'total_applied' => 0,
            'remaining_balance' => $totalAmount,
        ]);
    }

    /**
     * Create an unpaid leave deduction.
     */
    public function unpaidLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Deduction,
            'adjustment_type' => AdjustmentType::DeductionUnpaidLeave,
            'name' => 'Unpaid Leave Deduction',
            'amount' => fake()->randomFloat(2, 500, 5000),
            'is_taxable' => false,
            'frequency' => AdjustmentFrequency::OneTime,
        ]);
    }

    /**
     * Set the target payroll period for one-time adjustments.
     */
    public function forPeriod(PayrollPeriod|int $period): static
    {
        $periodId = $period instanceof PayrollPeriod ? $period->id : $period;

        return $this->state(fn (array $attributes) => [
            'frequency' => AdjustmentFrequency::OneTime,
            'target_payroll_period_id' => $periodId,
        ]);
    }

    /**
     * Create an adjustment for a specific employee.
     */
    public function forEmployee(Employee|int $employee): static
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId,
        ]);
    }

    /**
     * Create an adjustment in active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdjustmentStatus::Active,
        ]);
    }

    /**
     * Create a completed adjustment.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $updates = ['status' => AdjustmentStatus::Completed];

            if ($attributes['has_balance_tracking'] ?? false) {
                $totalAmount = $attributes['total_amount'] ?? 10000;
                $updates['total_applied'] = $totalAmount;
                $updates['remaining_balance'] = 0;
            }

            return $updates;
        });
    }

    /**
     * Create an adjustment on hold.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdjustmentStatus::OnHold,
            'metadata' => [
                'on_hold_at' => now()->subDays(fake()->numberBetween(1, 30))->toDateTimeString(),
                'on_hold_reason' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * Create a cancelled adjustment.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdjustmentStatus::Cancelled,
            'metadata' => [
                'cancelled_at' => now()->subDays(fake()->numberBetween(1, 30))->toDateTimeString(),
                'cancellation_reason' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * Create an adjustment with partial payment (for balance-tracking adjustments).
     */
    public function withPartialPayment(float $percentPaid = 0.5): static
    {
        return $this->state(function (array $attributes) use ($percentPaid) {
            if (! ($attributes['has_balance_tracking'] ?? false)) {
                return [];
            }

            $totalAmount = $attributes['total_amount'] ?? 10000;
            $totalApplied = $totalAmount * $percentPaid;

            return [
                'total_applied' => round($totalApplied, 2),
                'remaining_balance' => round($totalAmount - $totalApplied, 2),
            ];
        });
    }

    /**
     * Create an earning adjustment.
     */
    public function earning(): static
    {
        $types = AdjustmentType::earningTypes();
        $type = fake()->randomElement($types);

        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Earning,
            'adjustment_type' => $type,
            'name' => $type->label(),
        ]);
    }

    /**
     * Create a deduction adjustment.
     */
    public function deduction(): static
    {
        $types = AdjustmentType::deductionTypes();
        $type = fake()->randomElement($types);

        return $this->state(fn (array $attributes) => [
            'adjustment_category' => AdjustmentCategory::Deduction,
            'adjustment_type' => $type,
            'name' => $type->label(),
        ]);
    }

    /**
     * Create a one-time allowance adjustment.
     */
    public function oneTimeAllowance(): static
    {
        return $this->oneTime()->transportationAllowance();
    }

    /**
     * Create a recurring deduction adjustment with balance tracking.
     */
    public function recurringDeduction(): static
    {
        return $this->recurring()->salaryAdvance();
    }
}
