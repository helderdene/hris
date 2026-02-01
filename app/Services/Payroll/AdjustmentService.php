<?php

namespace App\Services\Payroll;

use App\Enums\AdjustmentCategory;
use App\Enums\DeductionType;
use App\Enums\EarningType;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

/**
 * Service for handling payroll adjustments (allowances, bonuses, deductions, loans).
 *
 * Calculates applicable adjustments for a payroll period and integrates
 * with the earnings and deductions calculators.
 */
class AdjustmentService
{
    /**
     * Get all applicable adjustments for an employee in a payroll period.
     *
     * @return Collection<int, EmployeeAdjustment>
     */
    public function getApplicableAdjustments(
        Employee $employee,
        PayrollPeriod $period,
        ?AdjustmentCategory $category = null
    ): Collection {
        $query = EmployeeAdjustment::query()
            ->forEmployee($employee)
            ->applicableForPeriod($period);

        if ($category) {
            $query->ofCategory($category);
        }

        return $query->get();
    }

    /**
     * Calculate earning adjustments (allowances, bonuses) for an employee.
     *
     * Returns the total amount and populates line items for payslip display.
     */
    public function calculateEarningAdjustments(
        Employee $employee,
        PayrollPeriod $period,
        Collection $lineItems
    ): float {
        $adjustments = $this->getApplicableAdjustments(
            $employee,
            $period,
            AdjustmentCategory::Earning
        );

        $total = 0;

        foreach ($adjustments as $adjustment) {
            $amount = $adjustment->getAmountForPeriod();

            if ($amount <= 0) {
                continue;
            }

            $earningType = $adjustment->adjustment_type->earningType() ?? EarningType::Adjustment;

            $lineItems->push([
                'earning_type' => $earningType,
                'earning_code' => $adjustment->adjustment_code,
                'description' => $adjustment->name,
                'quantity' => 1,
                'quantity_unit' => 'adjustment',
                'rate' => $amount,
                'multiplier' => 1.00,
                'amount' => $amount,
                'is_taxable' => $adjustment->is_taxable,
                'adjustment_id' => $adjustment->id,
            ]);

            $total += $amount;
        }

        return $total;
    }

    /**
     * Calculate allowance adjustments specifically.
     */
    public function calculateAllowanceAdjustments(
        Employee $employee,
        PayrollPeriod $period,
        Collection $lineItems
    ): float {
        $adjustments = $this->getApplicableAdjustments(
            $employee,
            $period,
            AdjustmentCategory::Earning
        )->filter(fn ($adj) => $adj->adjustment_type->isAllowance());

        $total = 0;

        foreach ($adjustments as $adjustment) {
            $amount = $adjustment->getAmountForPeriod();

            if ($amount <= 0) {
                continue;
            }

            $lineItems->push([
                'earning_type' => EarningType::Allowance,
                'earning_code' => $adjustment->adjustment_code,
                'description' => $adjustment->name,
                'quantity' => 1,
                'quantity_unit' => 'adjustment',
                'rate' => $amount,
                'multiplier' => 1.00,
                'amount' => $amount,
                'is_taxable' => $adjustment->is_taxable,
                'adjustment_id' => $adjustment->id,
            ]);

            $total += $amount;
        }

        return $total;
    }

    /**
     * Calculate bonus adjustments specifically.
     */
    public function calculateBonusAdjustments(
        Employee $employee,
        PayrollPeriod $period,
        Collection $lineItems
    ): float {
        $adjustments = $this->getApplicableAdjustments(
            $employee,
            $period,
            AdjustmentCategory::Earning
        )->filter(fn ($adj) => $adj->adjustment_type->isBonus());

        $total = 0;

        foreach ($adjustments as $adjustment) {
            $amount = $adjustment->getAmountForPeriod();

            if ($amount <= 0) {
                continue;
            }

            $lineItems->push([
                'earning_type' => EarningType::Bonus,
                'earning_code' => $adjustment->adjustment_code,
                'description' => $adjustment->name,
                'quantity' => 1,
                'quantity_unit' => 'adjustment',
                'rate' => $amount,
                'multiplier' => 1.00,
                'amount' => $amount,
                'is_taxable' => $adjustment->is_taxable,
                'adjustment_id' => $adjustment->id,
            ]);

            $total += $amount;
        }

        return $total;
    }

    /**
     * Calculate deduction adjustments (excluding loans handled by LoanDeductionService).
     */
    public function calculateDeductionAdjustments(
        Employee $employee,
        PayrollPeriod $period,
        Collection $lineItems
    ): float {
        $adjustments = $this->getApplicableAdjustments(
            $employee,
            $period,
            AdjustmentCategory::Deduction
        );

        $total = 0;

        foreach ($adjustments as $adjustment) {
            $amount = $adjustment->getAmountForPeriod();

            if ($amount <= 0) {
                continue;
            }

            $deductionType = $adjustment->adjustment_type->isLoan()
                ? DeductionType::Loan
                : DeductionType::Other;

            $lineItems->push([
                'deduction_type' => $deductionType,
                'deduction_code' => $adjustment->adjustment_code,
                'description' => $adjustment->name,
                'basis_amount' => $adjustment->has_balance_tracking ? $adjustment->total_amount : $amount,
                'rate' => 0,
                'amount' => $amount,
                'is_employee_share' => true,
                'is_employer_share' => false,
                'adjustment_id' => $adjustment->id,
                'has_balance_tracking' => $adjustment->has_balance_tracking,
                'remaining_balance' => $adjustment->remaining_balance,
            ]);

            $total += $amount;
        }

        return $total;
    }

    /**
     * Process adjustment applications after payroll computation.
     *
     * Records each adjustment application and updates balances.
     */
    public function processAdjustmentApplications(PayrollEntry $entry): void
    {
        $period = $entry->payrollPeriod;
        $employee = $entry->employee;

        $adjustments = $this->getApplicableAdjustments($employee, $period);

        foreach ($adjustments as $adjustment) {
            // Check if already applied
            if ($adjustment->applications()->where('payroll_period_id', $period->id)->exists()) {
                continue;
            }

            $amount = $adjustment->getAmountForPeriod();

            if ($amount <= 0) {
                continue;
            }

            $adjustment->recordApplication($period, $entry, $amount);
        }
    }

    /**
     * Get a summary of adjustments for an employee in a period.
     *
     * @return array{
     *     earnings: Collection,
     *     deductions: Collection,
     *     total_earnings: float,
     *     total_deductions: float
     * }
     */
    public function getAdjustmentSummary(Employee $employee, PayrollPeriod $period): array
    {
        $adjustments = $this->getApplicableAdjustments($employee, $period);

        $earnings = $adjustments->filter(fn ($adj) => $adj->isEarning());
        $deductions = $adjustments->filter(fn ($adj) => $adj->isDeduction());

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'total_earnings' => $earnings->sum(fn ($adj) => $adj->getAmountForPeriod()),
            'total_deductions' => $deductions->sum(fn ($adj) => $adj->getAmountForPeriod()),
        ];
    }
}
