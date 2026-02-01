<?php

namespace App\Services\Payroll;

use App\Enums\PayrollEntryStatus;
use App\Models\Employee;
use App\Models\PayrollDeduction;
use App\Models\PayrollEarning;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates payroll computation for employees.
 *
 * Coordinates DTR aggregation, earnings calculation, and deductions calculation
 * to produce complete payroll entries with detailed line items.
 */
class PayrollComputationService
{
    public function __construct(
        protected DtrAggregationService $dtrService,
        protected EarningsCalculator $earningsCalculator,
        protected DeductionsCalculator $deductionsCalculator
    ) {}

    /**
     * Compute payroll for all eligible employees in a period.
     *
     * @param  PayrollPeriod  $period  The payroll period to compute
     * @param  array<int>|null  $employeeIds  Specific employees to compute (null for all)
     * @param  bool  $forceRecompute  Whether to recompute existing entries
     * @return Collection<int, PayrollEntry>
     */
    public function computeForPeriod(
        PayrollPeriod $period,
        ?array $employeeIds = null,
        bool $forceRecompute = false
    ): Collection {
        $query = Employee::query()
            ->active()
            ->with(['compensation', 'department', 'position']);

        if ($employeeIds !== null) {
            $query->whereIn('id', $employeeIds);
        }

        $employees = $query->get();
        $entries = collect();

        foreach ($employees as $employee) {
            $existingEntry = PayrollEntry::query()
                ->where('payroll_period_id', $period->id)
                ->where('employee_id', $employee->id)
                ->first();

            if ($existingEntry && ! $forceRecompute) {
                continue;
            }

            if ($existingEntry && $forceRecompute) {
                if (! $existingEntry->canRecompute()) {
                    continue;
                }
            }

            $entry = $this->computeForEmployee($period, $employee, $existingEntry);

            if ($entry) {
                $entries->push($entry);
            }
        }

        $this->updatePeriodTotals($period);

        return $entries;
    }

    /**
     * Compute payroll for a single employee.
     */
    public function computeForEmployee(
        PayrollPeriod $period,
        Employee $employee,
        ?PayrollEntry $existingEntry = null
    ): ?PayrollEntry {
        if (! $employee->compensation) {
            return null;
        }

        return DB::transaction(function () use ($period, $employee, $existingEntry) {
            $dtrSummary = $this->dtrService->aggregateForPeriod($employee, $period);

            $earnings = $this->earningsCalculator->calculate($employee, $period, $dtrSummary);

            $deductions = $this->deductionsCalculator->calculate($employee, $period, $earnings['gross_pay']);

            $netPay = $earnings['gross_pay'] - $deductions['total_deductions'];

            $entryData = $this->buildEntryData($employee, $period, $dtrSummary, $earnings, $deductions, $netPay);

            if ($existingEntry) {
                $existingEntry->earnings()->delete();
                $existingEntry->deductions()->delete();
                $existingEntry->update($entryData);
                $entry = $existingEntry->fresh();
            } else {
                $entry = PayrollEntry::create($entryData);
            }

            $this->createEarningLineItems($entry, $earnings['line_items']);
            $this->createDeductionLineItems($entry, $deductions['line_items']);

            return $entry;
        });
    }

    /**
     * Recompute an existing payroll entry.
     */
    public function recompute(PayrollEntry $entry): ?PayrollEntry
    {
        if (! $entry->canRecompute()) {
            return null;
        }

        $period = $entry->payrollPeriod;
        $employee = $entry->employee;

        return $this->computeForEmployee($period, $employee, $entry);
    }

    /**
     * Preview payroll computation without saving.
     *
     * @return array<string, mixed>
     */
    public function preview(PayrollPeriod $period, Employee $employee): array
    {
        if (! $employee->compensation) {
            return ['error' => 'Employee has no compensation record'];
        }

        $dtrSummary = $this->dtrService->aggregateForPeriod($employee, $period);
        $earnings = $this->earningsCalculator->calculate($employee, $period, $dtrSummary);
        $deductions = $this->deductionsCalculator->calculate($employee, $period, $earnings['gross_pay']);
        $netPay = $earnings['gross_pay'] - $deductions['total_deductions'];

        return [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->full_name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'basic_salary' => $employee->compensation->basic_pay,
                'pay_type' => $employee->compensation->pay_type->label(),
            ],
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'cutoff_start' => $period->cutoff_start->toDateString(),
                'cutoff_end' => $period->cutoff_end->toDateString(),
            ],
            'dtr_summary' => [
                'days_worked' => $dtrSummary['days_worked'],
                'absent_days' => $dtrSummary['absent_days'],
                'late_minutes' => $dtrSummary['total_late_minutes'],
                'undertime_minutes' => $dtrSummary['total_undertime_minutes'],
                'overtime_minutes' => $dtrSummary['total_overtime_minutes'],
                'night_diff_minutes' => $dtrSummary['total_night_diff_minutes'],
                'holiday_days' => $dtrSummary['holiday_days'],
            ],
            'earnings' => [
                'basic_pay' => $earnings['basic_pay'],
                'overtime_pay' => $earnings['overtime_pay'],
                'night_diff_pay' => $earnings['night_diff_pay'],
                'holiday_pay' => $earnings['holiday_pay'],
                'allowances' => $earnings['allowances_total'],
                'bonuses' => $earnings['bonuses_total'],
                'gross_pay' => $earnings['gross_pay'],
                'line_items' => $earnings['line_items']->toArray(),
            ],
            'deductions' => [
                'sss_employee' => $deductions['sss_employee'],
                'philhealth_employee' => $deductions['philhealth_employee'],
                'pagibig_employee' => $deductions['pagibig_employee'],
                'withholding_tax' => $deductions['withholding_tax'],
                'other' => $deductions['other_deductions_total'],
                'total' => $deductions['total_deductions'],
                'employer_contributions' => $deductions['total_employer_contributions'],
                'line_items' => $deductions['line_items']->toArray(),
            ],
            'net_pay' => round($netPay, 2),
        ];
    }

    /**
     * Build the payroll entry data array.
     *
     * @return array<string, mixed>
     */
    protected function buildEntryData(
        Employee $employee,
        PayrollPeriod $period,
        array $dtrSummary,
        array $earnings,
        array $deductions,
        float $netPay
    ): array {
        return [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'department_name' => $employee->department?->name,
            'position_name' => $employee->position?->name,
            'basic_salary_snapshot' => $employee->compensation->basic_pay,
            'pay_type_snapshot' => $employee->compensation->pay_type,
            'days_worked' => $dtrSummary['days_worked'],
            'total_regular_minutes' => $dtrSummary['total_regular_minutes'],
            'total_late_minutes' => $dtrSummary['total_late_minutes'],
            'total_undertime_minutes' => $dtrSummary['total_undertime_minutes'],
            'total_overtime_minutes' => $dtrSummary['total_overtime_minutes'],
            'total_night_diff_minutes' => $dtrSummary['total_night_diff_minutes'],
            'absent_days' => $dtrSummary['absent_days'],
            'holiday_days' => $dtrSummary['holiday_days'],
            'basic_pay' => $earnings['basic_pay'],
            'overtime_pay' => $earnings['overtime_pay'],
            'night_diff_pay' => $earnings['night_diff_pay'],
            'holiday_pay' => $earnings['holiday_pay'],
            'allowances_total' => $earnings['allowances_total'],
            'bonuses_total' => $earnings['bonuses_total'],
            'gross_pay' => $earnings['gross_pay'],
            'sss_employee' => $deductions['sss_employee'],
            'sss_employer' => $deductions['sss_employer'],
            'philhealth_employee' => $deductions['philhealth_employee'],
            'philhealth_employer' => $deductions['philhealth_employer'],
            'pagibig_employee' => $deductions['pagibig_employee'],
            'pagibig_employer' => $deductions['pagibig_employer'],
            'withholding_tax' => $deductions['withholding_tax'],
            'other_deductions_total' => $deductions['other_deductions_total'],
            'total_deductions' => $deductions['total_deductions'],
            'total_employer_contributions' => $deductions['total_employer_contributions'],
            'net_pay' => round($netPay, 2),
            'status' => PayrollEntryStatus::Computed,
            'computed_at' => now(),
            'computed_by' => auth()->id(),
        ];
    }

    /**
     * Create earning line items for an entry.
     */
    protected function createEarningLineItems(PayrollEntry $entry, Collection $lineItems): void
    {
        foreach ($lineItems as $item) {
            PayrollEarning::create([
                'payroll_entry_id' => $entry->id,
                'earning_type' => $item['earning_type'],
                'earning_code' => $item['earning_code'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'quantity_unit' => $item['quantity_unit'],
                'rate' => $item['rate'],
                'multiplier' => $item['multiplier'],
                'amount' => $item['amount'],
                'is_taxable' => $item['is_taxable'],
            ]);
        }
    }

    /**
     * Create deduction line items for an entry.
     */
    protected function createDeductionLineItems(PayrollEntry $entry, Collection $lineItems): void
    {
        foreach ($lineItems as $item) {
            PayrollDeduction::create([
                'payroll_entry_id' => $entry->id,
                'deduction_type' => $item['deduction_type'],
                'deduction_code' => $item['deduction_code'],
                'description' => $item['description'],
                'basis_amount' => $item['basis_amount'],
                'rate' => $item['rate'],
                'amount' => $item['amount'],
                'is_employee_share' => $item['is_employee_share'],
                'is_employer_share' => $item['is_employer_share'],
                'contribution_table_type' => $item['contribution_table_type'] ?? null,
                'contribution_table_id' => $item['contribution_table_id'] ?? null,
            ]);
        }
    }

    /**
     * Update period totals after computation.
     */
    protected function updatePeriodTotals(PayrollPeriod $period): void
    {
        $totals = PayrollEntry::query()
            ->where('payroll_period_id', $period->id)
            ->selectRaw('
                COUNT(*) as employee_count,
                SUM(gross_pay) as total_gross,
                SUM(total_deductions) as total_deductions,
                SUM(net_pay) as total_net
            ')
            ->first();

        $period->update([
            'employee_count' => $totals->employee_count ?? 0,
            'total_gross' => $totals->total_gross ?? 0,
            'total_deductions' => $totals->total_deductions ?? 0,
            'total_net' => $totals->total_net ?? 0,
        ]);
    }
}
