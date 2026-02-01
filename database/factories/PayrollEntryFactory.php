<?php

namespace Database\Factories;

use App\Enums\PayrollEntryStatus;
use App\Enums\PayType;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollEntry>
 */
class PayrollEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PayrollEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basicSalary = fake()->randomFloat(2, 15000, 80000);
        $daysWorked = fake()->randomFloat(2, 10, 22);
        $basicPay = $basicSalary;
        $overtimePay = fake()->randomFloat(2, 0, 5000);
        $nightDiffPay = fake()->randomFloat(2, 0, 1000);
        $holidayPay = fake()->randomFloat(2, 0, 3000);
        $grossPay = $basicPay + $overtimePay + $nightDiffPay + $holidayPay;

        $sssEmployee = fake()->randomFloat(2, 500, 1500);
        $philhealthEmployee = fake()->randomFloat(2, 200, 800);
        $pagibigEmployee = 100;
        $withHoldingTax = fake()->randomFloat(2, 0, 5000);
        $totalDeductions = $sssEmployee + $philhealthEmployee + $pagibigEmployee + $withHoldingTax;
        $netPay = $grossPay - $totalDeductions;

        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'employee_number' => fake()->unique()->numerify('EMP-#####'),
            'employee_name' => fake()->name(),
            'department_name' => fake()->word().' Department',
            'position_name' => fake()->jobTitle(),
            'basic_salary_snapshot' => $basicSalary,
            'pay_type_snapshot' => fake()->randomElement(PayType::cases()),
            'days_worked' => $daysWorked,
            'total_regular_minutes' => (int) ($daysWorked * 8 * 60),
            'total_late_minutes' => fake()->numberBetween(0, 120),
            'total_undertime_minutes' => fake()->numberBetween(0, 60),
            'total_overtime_minutes' => fake()->numberBetween(0, 480),
            'total_night_diff_minutes' => fake()->numberBetween(0, 240),
            'absent_days' => fake()->randomFloat(2, 0, 3),
            'holiday_days' => fake()->randomFloat(2, 0, 2),
            'basic_pay' => $basicPay,
            'overtime_pay' => $overtimePay,
            'night_diff_pay' => $nightDiffPay,
            'holiday_pay' => $holidayPay,
            'allowances_total' => 0,
            'bonuses_total' => 0,
            'gross_pay' => $grossPay,
            'sss_employee' => $sssEmployee,
            'sss_employer' => $sssEmployee * 1.5,
            'philhealth_employee' => $philhealthEmployee,
            'philhealth_employer' => $philhealthEmployee,
            'pagibig_employee' => $pagibigEmployee,
            'pagibig_employer' => $pagibigEmployee * 2,
            'withholding_tax' => $withHoldingTax,
            'other_deductions_total' => 0,
            'total_deductions' => $totalDeductions,
            'total_employer_contributions' => ($sssEmployee * 1.5) + $philhealthEmployee + ($pagibigEmployee * 2),
            'net_pay' => $netPay,
            'status' => PayrollEntryStatus::Draft,
            'computed_at' => null,
            'computed_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'remarks' => null,
        ];
    }

    /**
     * Create a draft entry.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollEntryStatus::Draft,
            'computed_at' => null,
            'computed_by' => null,
        ]);
    }

    /**
     * Create a computed entry.
     */
    public function computed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollEntryStatus::Computed,
            'computed_at' => now(),
        ]);
    }

    /**
     * Create a reviewed entry.
     */
    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollEntryStatus::Reviewed,
            'computed_at' => now()->subHours(2),
        ]);
    }

    /**
     * Create an approved entry.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollEntryStatus::Approved,
            'computed_at' => now()->subHours(4),
            'approved_at' => now()->subHours(1),
        ]);
    }

    /**
     * Create a paid entry.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollEntryStatus::Paid,
            'computed_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
        ]);
    }

    /**
     * Set a specific employee for the entry.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'department_name' => $employee->department?->name,
            'position_name' => $employee->position?->name,
        ]);
    }

    /**
     * Set a specific payroll period for the entry.
     */
    public function forPeriod(PayrollPeriod $period): static
    {
        return $this->state(fn (array $attributes) => [
            'payroll_period_id' => $period->id,
        ]);
    }

    /**
     * Create an entry for a monthly employee.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_type_snapshot' => PayType::Monthly,
        ]);
    }

    /**
     * Create an entry for a daily employee.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_type_snapshot' => PayType::Daily,
        ]);
    }

    /**
     * Set specific compensation values.
     */
    public function withCompensation(
        float $basicPay,
        float $overtimePay = 0,
        float $nightDiffPay = 0,
        float $holidayPay = 0
    ): static {
        $grossPay = $basicPay + $overtimePay + $nightDiffPay + $holidayPay;

        return $this->state(fn (array $attributes) => [
            'basic_pay' => $basicPay,
            'overtime_pay' => $overtimePay,
            'night_diff_pay' => $nightDiffPay,
            'holiday_pay' => $holidayPay,
            'gross_pay' => $grossPay,
        ]);
    }

    /**
     * Set specific deduction values.
     */
    public function withDeductions(
        float $sssEmployee,
        float $philhealthEmployee,
        float $pagibigEmployee,
        float $withHoldingTax
    ): static {
        return $this->state(function (array $attributes) use ($sssEmployee, $philhealthEmployee, $pagibigEmployee, $withHoldingTax) {
            $totalDeductions = $sssEmployee + $philhealthEmployee + $pagibigEmployee + $withHoldingTax;
            $netPay = $attributes['gross_pay'] - $totalDeductions;

            return [
                'sss_employee' => $sssEmployee,
                'philhealth_employee' => $philhealthEmployee,
                'pagibig_employee' => $pagibigEmployee,
                'withholding_tax' => $withHoldingTax,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
            ];
        });
    }
}
