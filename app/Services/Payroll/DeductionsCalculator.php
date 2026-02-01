<?php

namespace App\Services\Payroll;

use App\Enums\DeductionType;
use App\Enums\PayrollCycleType;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\ContributionCalculatorService;
use Illuminate\Support\Collection;

/**
 * Calculates deductions for payroll computation.
 *
 * Handles government-mandated contributions (SSS, PhilHealth, Pag-IBIG),
 * withholding tax, and other deductions with proper semi-monthly splitting.
 */
class DeductionsCalculator
{
    public function __construct(
        protected ContributionCalculatorService $contributionService,
        protected AdjustmentService $adjustmentService
    ) {}

    /**
     * Calculate all deductions for an employee in a payroll period.
     *
     * @return array{
     *     sss_employee: float,
     *     sss_employer: float,
     *     philhealth_employee: float,
     *     philhealth_employer: float,
     *     pagibig_employee: float,
     *     pagibig_employer: float,
     *     withholding_tax: float,
     *     other_deductions_total: float,
     *     total_deductions: float,
     *     total_employer_contributions: float,
     *     line_items: Collection
     * }
     */
    public function calculate(Employee $employee, PayrollPeriod $period, float $grossPay): array
    {
        $lineItems = collect();

        $cycleType = $period->payrollCycle?->cycle_type ?? PayrollCycleType::SemiMonthly;
        $isSecondCutoff = $period->period_number % 2 === 0;

        $monthlySalary = $this->getMonthlyEquivalent($employee, $grossPay, $cycleType);

        $sss = $this->calculateSss($monthlySalary, $period, $cycleType, $isSecondCutoff, $lineItems);
        $philhealth = $this->calculatePhilHealth($monthlySalary, $period, $cycleType, $isSecondCutoff, $lineItems);
        $pagibig = $this->calculatePagibig($monthlySalary, $period, $cycleType, $isSecondCutoff, $lineItems);

        $preTaxDeductions = $sss['employee'] + $philhealth['employee'] + $pagibig['employee'];
        $taxableIncome = max(0, $grossPay - $preTaxDeductions);

        $withHoldingTax = $this->calculateWithholdingTax(
            $taxableIncome,
            $period,
            $cycleType,
            $lineItems
        );

        $otherDeductions = $this->calculateOtherDeductions($employee, $period, $lineItems);

        $totalEmployeeDeductions = $sss['employee']
            + $philhealth['employee']
            + $pagibig['employee']
            + $withHoldingTax
            + $otherDeductions;

        $totalEmployerContributions = $sss['employer']
            + $philhealth['employer']
            + $pagibig['employer'];

        return [
            'sss_employee' => round($sss['employee'], 2),
            'sss_employer' => round($sss['employer'], 2),
            'philhealth_employee' => round($philhealth['employee'], 2),
            'philhealth_employer' => round($philhealth['employer'], 2),
            'pagibig_employee' => round($pagibig['employee'], 2),
            'pagibig_employer' => round($pagibig['employer'], 2),
            'withholding_tax' => round($withHoldingTax, 2),
            'other_deductions_total' => round($otherDeductions, 2),
            'total_deductions' => round($totalEmployeeDeductions, 2),
            'total_employer_contributions' => round($totalEmployerContributions, 2),
            'line_items' => $lineItems,
        ];
    }

    /**
     * Get monthly equivalent salary for contribution calculation.
     */
    protected function getMonthlyEquivalent(Employee $employee, float $grossPay, PayrollCycleType $cycleType): float
    {
        $compensation = $employee->compensation;

        if ($compensation) {
            return (float) $compensation->basic_pay * match ($compensation->pay_type->value) {
                'monthly' => 1,
                'semi_monthly' => 2,
                'weekly' => 4.33,
                'daily' => 22,
            };
        }

        return match ($cycleType) {
            PayrollCycleType::SemiMonthly => $grossPay * 2,
            PayrollCycleType::Monthly => $grossPay,
            default => $grossPay,
        };
    }

    /**
     * Calculate SSS contribution.
     *
     * SSS is typically deducted fully on the second cutoff for semi-monthly payroll.
     *
     * @return array{employee: float, employer: float}
     */
    protected function calculateSss(
        float $monthlySalary,
        PayrollPeriod $period,
        PayrollCycleType $cycleType,
        bool $isSecondCutoff,
        Collection $lineItems
    ): array {
        if ($cycleType === PayrollCycleType::SemiMonthly && ! $isSecondCutoff) {
            return ['employee' => 0, 'employer' => 0];
        }

        $sss = $this->contributionService->calculateSss($monthlySalary, $period->cutoff_end);

        if ($sss['error']) {
            return ['employee' => 0, 'employer' => 0];
        }

        $lineItems->push([
            'deduction_type' => DeductionType::Sss,
            'deduction_code' => 'SSS_EE',
            'description' => 'SSS Employee Share',
            'basis_amount' => $monthlySalary,
            'rate' => 0.045,
            'amount' => $sss['employee_share'],
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'sss_contribution_tables',
            'contribution_table_id' => $sss['table_id'],
        ]);

        $lineItems->push([
            'deduction_type' => DeductionType::Sss,
            'deduction_code' => 'SSS_ER',
            'description' => 'SSS Employer Share',
            'basis_amount' => $monthlySalary,
            'rate' => 0.095,
            'amount' => $sss['employer_share'],
            'is_employee_share' => false,
            'is_employer_share' => true,
            'contribution_table_type' => 'sss_contribution_tables',
            'contribution_table_id' => $sss['table_id'],
        ]);

        return [
            'employee' => $sss['employee_share'],
            'employer' => $sss['employer_share'],
        ];
    }

    /**
     * Calculate PhilHealth contribution.
     *
     * PhilHealth is split evenly across both cutoffs for semi-monthly payroll.
     *
     * @return array{employee: float, employer: float}
     */
    protected function calculatePhilHealth(
        float $monthlySalary,
        PayrollPeriod $period,
        PayrollCycleType $cycleType,
        bool $isSecondCutoff,
        Collection $lineItems
    ): array {
        $philhealth = $this->contributionService->calculatePhilHealth($monthlySalary, $period->cutoff_end);

        if ($philhealth['error']) {
            return ['employee' => 0, 'employer' => 0];
        }

        $divisor = $cycleType === PayrollCycleType::SemiMonthly ? 2 : 1;
        $employeeShare = $philhealth['employee_share'] / $divisor;
        $employerShare = $philhealth['employer_share'] / $divisor;

        $lineItems->push([
            'deduction_type' => DeductionType::Philhealth,
            'deduction_code' => 'PHIC_EE',
            'description' => 'PhilHealth Employee Share',
            'basis_amount' => $monthlySalary,
            'rate' => 0.025,
            'amount' => $employeeShare,
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'philhealth_contribution_tables',
            'contribution_table_id' => $philhealth['table_id'],
        ]);

        $lineItems->push([
            'deduction_type' => DeductionType::Philhealth,
            'deduction_code' => 'PHIC_ER',
            'description' => 'PhilHealth Employer Share',
            'basis_amount' => $monthlySalary,
            'rate' => 0.025,
            'amount' => $employerShare,
            'is_employee_share' => false,
            'is_employer_share' => true,
            'contribution_table_type' => 'philhealth_contribution_tables',
            'contribution_table_id' => $philhealth['table_id'],
        ]);

        return [
            'employee' => $employeeShare,
            'employer' => $employerShare,
        ];
    }

    /**
     * Calculate Pag-IBIG contribution.
     *
     * Pag-IBIG is typically deducted fully on the second cutoff for semi-monthly payroll.
     *
     * @return array{employee: float, employer: float}
     */
    protected function calculatePagibig(
        float $monthlySalary,
        PayrollPeriod $period,
        PayrollCycleType $cycleType,
        bool $isSecondCutoff,
        Collection $lineItems
    ): array {
        if ($cycleType === PayrollCycleType::SemiMonthly && ! $isSecondCutoff) {
            return ['employee' => 0, 'employer' => 0];
        }

        $pagibig = $this->contributionService->calculatePagibig($monthlySalary, $period->cutoff_end);

        if ($pagibig['error']) {
            return ['employee' => 0, 'employer' => 0];
        }

        $lineItems->push([
            'deduction_type' => DeductionType::Pagibig,
            'deduction_code' => 'HDMF_EE',
            'description' => 'Pag-IBIG Employee Share',
            'basis_amount' => $monthlySalary,
            'rate' => 0.02,
            'amount' => $pagibig['employee_share'],
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'pagibig_contribution_tables',
            'contribution_table_id' => $pagibig['table_id'],
        ]);

        $lineItems->push([
            'deduction_type' => DeductionType::Pagibig,
            'deduction_code' => 'HDMF_ER',
            'description' => 'Pag-IBIG Employer Share',
            'basis_amount' => $monthlySalary,
            'rate' => 0.02,
            'amount' => $pagibig['employer_share'],
            'is_employee_share' => false,
            'is_employer_share' => true,
            'contribution_table_type' => 'pagibig_contribution_tables',
            'contribution_table_id' => $pagibig['table_id'],
        ]);

        return [
            'employee' => $pagibig['employee_share'],
            'employer' => $pagibig['employer_share'],
        ];
    }

    /**
     * Calculate withholding tax.
     */
    protected function calculateWithholdingTax(
        float $taxableIncome,
        PayrollPeriod $period,
        PayrollCycleType $cycleType,
        Collection $lineItems
    ): float {
        $payPeriod = match ($cycleType) {
            PayrollCycleType::SemiMonthly => 'semi_monthly',
            PayrollCycleType::Monthly => 'monthly',
            default => 'monthly',
        };

        $tax = $this->contributionService->calculateTax($taxableIncome, $period->cutoff_end, $payPeriod);

        if ($tax['error'] || $tax['tax_due'] <= 0) {
            return 0;
        }

        $lineItems->push([
            'deduction_type' => DeductionType::WithholdingTax,
            'deduction_code' => 'TAX',
            'description' => 'Withholding Tax',
            'basis_amount' => $taxableIncome,
            'rate' => 0,
            'amount' => $tax['tax_due'],
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'withholding_tax_tables',
            'contribution_table_id' => $tax['table_id'],
        ]);

        return $tax['tax_due'];
    }

    /**
     * Calculate other deductions including loans and adjustment deductions.
     */
    protected function calculateOtherDeductions(Employee $employee, PayrollPeriod $period, Collection $lineItems): float
    {
        $loanService = app(LoanDeductionService::class);

        // Loan deductions from EmployeeLoan model
        $loanDeductions = $loanService->calculateLoanDeductions($employee, $period, $lineItems);

        // Adjustment-based deductions (including loan-type adjustments)
        $adjustmentDeductions = $this->adjustmentService->calculateDeductionAdjustments(
            $employee,
            $period,
            $lineItems
        );

        return $loanDeductions + $adjustmentDeductions;
    }
}
