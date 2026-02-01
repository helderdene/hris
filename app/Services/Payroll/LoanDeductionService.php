<?php

namespace App\Services\Payroll;

use App\Enums\DeductionType;
use App\Enums\PayrollCycleType;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

/**
 * Service for calculating and processing loan deductions in payroll.
 *
 * Handles automatic deduction of active employee loans during payroll computation
 * and records payments when payroll is finalized.
 */
class LoanDeductionService
{
    /**
     * Get all deductible loans for an employee.
     *
     * @return Collection<int, EmployeeLoan>
     */
    public function getDeductibleLoans(Employee $employee): Collection
    {
        return $employee->loans()
            ->deductible()
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Calculate loan deductions for an employee in a payroll period.
     *
     * Loan deductions are only processed on the second cutoff of semi-monthly payroll.
     * For monthly payroll, deductions are processed every period.
     *
     * @param  Collection  $lineItems  Collection to add deduction line items to
     * @return float Total loan deduction amount
     */
    public function calculateLoanDeductions(
        Employee $employee,
        PayrollPeriod $period,
        Collection $lineItems
    ): float {
        $cycleType = $period->payrollCycle?->cycle_type ?? PayrollCycleType::SemiMonthly;
        $isSecondCutoff = $period->period_number % 2 === 0;

        if ($cycleType === PayrollCycleType::SemiMonthly && ! $isSecondCutoff) {
            return 0;
        }

        $loans = $this->getDeductibleLoans($employee);

        if ($loans->isEmpty()) {
            return 0;
        }

        $totalDeduction = 0;

        foreach ($loans as $loan) {
            $deductionAmount = $loan->getDeductionAmount();

            if ($deductionAmount <= 0) {
                continue;
            }

            $lineItems->push([
                'deduction_type' => DeductionType::Loan,
                'deduction_code' => $this->generateDeductionCode($loan),
                'description' => $this->generateDescription($loan),
                'basis_amount' => (float) $loan->remaining_balance,
                'rate' => 0,
                'amount' => $deductionAmount,
                'is_employee_share' => true,
                'is_employer_share' => false,
                'loan_id' => $loan->id,
                'loan_type' => $loan->loan_type->value,
                'loan_code' => $loan->loan_code,
            ]);

            $totalDeduction += $deductionAmount;
        }

        return $totalDeduction;
    }

    /**
     * Process loan payments after payroll is finalized.
     *
     * Records payments against loans based on the deductions in the payroll entry.
     */
    public function processLoanPayments(PayrollEntry $payrollEntry): void
    {
        $loanDeductions = $payrollEntry->deductions()
            ->where('deduction_type', DeductionType::Loan)
            ->get();

        foreach ($loanDeductions as $deduction) {
            $loanId = $deduction->remarks ? json_decode($deduction->remarks, true)['loan_id'] ?? null : null;

            if (! $loanId) {
                continue;
            }

            $loan = EmployeeLoan::find($loanId);

            if (! $loan) {
                continue;
            }

            $loan->recordPayment(
                amount: (float) $deduction->amount,
                paymentDate: $payrollEntry->payrollPeriod->pay_date->toDateString(),
                paymentSource: 'payroll',
                payrollDeductionId: $deduction->id,
                notes: "Payroll Period: {$payrollEntry->payrollPeriod->name}"
            );
        }
    }

    /**
     * Generate a deduction code for a loan.
     */
    protected function generateDeductionCode(EmployeeLoan $loan): string
    {
        return 'LOAN_'.strtoupper($loan->loan_type->category()).'_'.$loan->id;
    }

    /**
     * Generate a description for the loan deduction.
     */
    protected function generateDescription(EmployeeLoan $loan): string
    {
        $description = $loan->loan_type->label();

        if ($loan->reference_number) {
            $description .= " ({$loan->reference_number})";
        }

        return $description;
    }

    /**
     * Get total loan deduction amount for an employee.
     *
     * Useful for previewing total deductions without creating line items.
     */
    public function getTotalDeductionAmount(Employee $employee): float
    {
        return $this->getDeductibleLoans($employee)
            ->sum(fn (EmployeeLoan $loan) => $loan->getDeductionAmount());
    }

    /**
     * Get loan deduction summary for an employee.
     *
     * @return array<string, array{loans: Collection, total: float}>
     */
    public function getDeductionSummaryByCategory(Employee $employee): array
    {
        $loans = $this->getDeductibleLoans($employee);
        $summary = [];

        foreach ($loans as $loan) {
            $category = $loan->loan_type->category();

            if (! isset($summary[$category])) {
                $summary[$category] = [
                    'loans' => collect(),
                    'total' => 0,
                ];
            }

            $summary[$category]['loans']->push($loan);
            $summary[$category]['total'] += $loan->getDeductionAmount();
        }

        return $summary;
    }
}
