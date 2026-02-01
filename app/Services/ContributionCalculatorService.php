<?php

namespace App\Services;

use App\Models\PagibigContributionTable;
use App\Models\PhilhealthContributionTable;
use App\Models\SssContributionTable;
use App\Models\WithholdingTaxTable;
use Carbon\Carbon;

/**
 * Service for calculating government contributions (SSS, PhilHealth, Pag-IBIG)
 * and withholding tax.
 *
 * This service provides methods to calculate contributions based on salary
 * and effective date, using the appropriate contribution tables.
 */
class ContributionCalculatorService
{
    /**
     * Calculate SSS contribution for a given salary.
     *
     * @param  float  $salary  Monthly salary
     * @param  Carbon|null  $effectiveDate  Date to use for table lookup (defaults to now)
     * @return array{
     *     employee_share: float,
     *     employer_share: float,
     *     total: float,
     *     ec_contribution: float,
     *     monthly_salary_credit: float,
     *     table_id: int|null,
     *     error: string|null
     * }
     */
    public function calculateSss(float $salary, ?Carbon $effectiveDate = null): array
    {
        $effectiveDate = $effectiveDate ?? now();

        $table = SssContributionTable::effectiveAt($effectiveDate);

        if (! $table) {
            return [
                'employee_share' => 0,
                'employer_share' => 0,
                'total' => 0,
                'ec_contribution' => 0,
                'monthly_salary_credit' => 0,
                'table_id' => null,
                'error' => 'No active SSS contribution table found for the specified date.',
            ];
        }

        $bracket = $table->findBracketForSalary($salary);

        if (! $bracket) {
            return [
                'employee_share' => 0,
                'employer_share' => 0,
                'total' => 0,
                'ec_contribution' => 0,
                'monthly_salary_credit' => 0,
                'table_id' => $table->id,
                'error' => 'No matching SSS bracket found for the given salary.',
            ];
        }

        return [
            'employee_share' => (float) $bracket->employee_contribution,
            'employer_share' => (float) $bracket->employer_contribution,
            'total' => (float) $bracket->total_contribution,
            'ec_contribution' => (float) $bracket->ec_contribution,
            'monthly_salary_credit' => (float) $bracket->monthly_salary_credit,
            'table_id' => $table->id,
            'error' => null,
        ];
    }

    /**
     * Calculate PhilHealth contribution for a given salary.
     *
     * @param  float  $salary  Monthly salary
     * @param  Carbon|null  $effectiveDate  Date to use for table lookup (defaults to now)
     * @return array{
     *     employee_share: float,
     *     employer_share: float,
     *     total: float,
     *     basis_salary: float,
     *     table_id: int|null,
     *     error: string|null
     * }
     */
    public function calculatePhilHealth(float $salary, ?Carbon $effectiveDate = null): array
    {
        $effectiveDate = $effectiveDate ?? now();

        $table = PhilhealthContributionTable::effectiveAt($effectiveDate);

        if (! $table) {
            return [
                'employee_share' => 0,
                'employer_share' => 0,
                'total' => 0,
                'basis_salary' => 0,
                'table_id' => null,
                'error' => 'No active PhilHealth contribution table found for the specified date.',
            ];
        }

        $contribution = $table->calculateContribution($salary);

        return [
            'employee_share' => $contribution['employee_share'],
            'employer_share' => $contribution['employer_share'],
            'total' => $contribution['total'],
            'basis_salary' => $contribution['basis_salary'],
            'table_id' => $table->id,
            'error' => null,
        ];
    }

    /**
     * Calculate Pag-IBIG contribution for a given salary.
     *
     * @param  float  $salary  Monthly salary
     * @param  Carbon|null  $effectiveDate  Date to use for table lookup (defaults to now)
     * @return array{
     *     employee_share: float,
     *     employer_share: float,
     *     total: float,
     *     basis_salary: float,
     *     table_id: int|null,
     *     error: string|null
     * }
     */
    public function calculatePagibig(float $salary, ?Carbon $effectiveDate = null): array
    {
        $effectiveDate = $effectiveDate ?? now();

        $table = PagibigContributionTable::effectiveAt($effectiveDate);

        if (! $table) {
            return [
                'employee_share' => 0,
                'employer_share' => 0,
                'total' => 0,
                'basis_salary' => 0,
                'table_id' => null,
                'error' => 'No active Pag-IBIG contribution table found for the specified date.',
            ];
        }

        $contribution = $table->calculateContribution($salary);

        return [
            'employee_share' => $contribution['employee_share'],
            'employer_share' => $contribution['employer_share'],
            'total' => $contribution['total'],
            'basis_salary' => $contribution['basis_salary'],
            'table_id' => $table->id,
            'error' => null,
        ];
    }

    /**
     * Calculate withholding tax for a given taxable income.
     *
     * Note: Taxable income = Gross Salary - SSS - PhilHealth - Pag-IBIG employee shares.
     * This method assumes monthly pay period for calculation.
     *
     * @param  float  $taxableIncome  Monthly taxable income (after deductions)
     * @param  Carbon|null  $effectiveDate  Date to use for table lookup (defaults to now)
     * @param  string  $payPeriod  Pay period type (defaults to 'monthly')
     * @return array{
     *     tax_due: float,
     *     taxable_income: float,
     *     table_id: int|null,
     *     pay_period: string,
     *     error: string|null
     * }
     */
    public function calculateTax(
        float $taxableIncome,
        ?Carbon $effectiveDate = null,
        string $payPeriod = 'monthly'
    ): array {
        $effectiveDate = $effectiveDate ?? now();

        $table = WithholdingTaxTable::effectiveAt($effectiveDate, $payPeriod);

        if (! $table) {
            return [
                'tax_due' => 0,
                'taxable_income' => $taxableIncome,
                'table_id' => null,
                'pay_period' => $payPeriod,
                'error' => "No active withholding tax table found for {$payPeriod} pay period.",
            ];
        }

        $taxDue = $table->calculateTax($taxableIncome);

        return [
            'tax_due' => round($taxDue, 2),
            'taxable_income' => $taxableIncome,
            'table_id' => $table->id,
            'pay_period' => $payPeriod,
            'error' => null,
        ];
    }

    /**
     * Calculate all government contributions for a given salary.
     *
     * @param  float  $salary  Monthly salary
     * @param  Carbon|null  $effectiveDate  Date to use for table lookup (defaults to now)
     * @return array{
     *     sss: array,
     *     philhealth: array,
     *     pagibig: array,
     *     tax: array,
     *     totals: array{
     *         employee_share: float,
     *         employer_share: float,
     *         total: float
     *     }
     * }
     */
    public function calculateAll(float $salary, ?Carbon $effectiveDate = null): array
    {
        $sss = $this->calculateSss($salary, $effectiveDate);
        $philHealth = $this->calculatePhilHealth($salary, $effectiveDate);
        $pagibig = $this->calculatePagibig($salary, $effectiveDate);

        // Calculate taxable income (gross - mandatory deductions)
        $totalDeductions = $sss['employee_share'] + $philHealth['employee_share'] + $pagibig['employee_share'];
        $taxableIncome = $salary - $totalDeductions;

        // Calculate withholding tax based on taxable income
        $tax = $this->calculateTax($taxableIncome, $effectiveDate, 'monthly');

        return [
            'sss' => $sss,
            'philhealth' => $philHealth,
            'pagibig' => $pagibig,
            'tax' => $tax,
            'totals' => [
                'employee_share' => $sss['employee_share'] + $philHealth['employee_share'] + $pagibig['employee_share'],
                'employer_share' => $sss['employer_share'] + $philHealth['employer_share'] + $pagibig['employer_share'],
                'total' => $sss['total'] + $philHealth['total'] + $pagibig['total'],
                'tax_due' => $tax['tax_due'],
                'total_employee_deductions' => $sss['employee_share'] + $philHealth['employee_share'] + $pagibig['employee_share'] + $tax['tax_due'],
                'net_pay' => $salary - ($sss['employee_share'] + $philHealth['employee_share'] + $pagibig['employee_share'] + $tax['tax_due']),
            ],
        ];
    }

    /**
     * Get all active contribution tables.
     *
     * @return array{
     *     sss: SssContributionTable|null,
     *     philhealth: PhilhealthContributionTable|null,
     *     pagibig: PagibigContributionTable|null,
     *     tax: WithholdingTaxTable|null
     * }
     */
    public function getActiveTables(): array
    {
        return [
            'sss' => SssContributionTable::current(),
            'philhealth' => PhilhealthContributionTable::current(),
            'pagibig' => PagibigContributionTable::current(),
            'tax' => WithholdingTaxTable::current('monthly'),
        ];
    }

    /**
     * Check if all contribution tables are configured.
     *
     * Note: Tax table is optional for this check since it may not be required
     * for all payroll scenarios.
     */
    public function hasAllTables(): bool
    {
        $tables = $this->getActiveTables();

        return $tables['sss'] !== null
            && $tables['philhealth'] !== null
            && $tables['pagibig'] !== null;
    }

    /**
     * Check if all contribution tables including tax are configured.
     */
    public function hasAllTablesIncludingTax(): bool
    {
        $tables = $this->getActiveTables();

        return $tables['sss'] !== null
            && $tables['philhealth'] !== null
            && $tables['pagibig'] !== null
            && $tables['tax'] !== null;
    }
}
