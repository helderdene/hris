<?php

namespace Database\Seeders;

use App\Enums\BankAccountType;
use App\Enums\DeductionType;
use App\Enums\EarningType;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\PayrollCycleType;
use App\Enums\PayrollEntryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayType;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use App\Models\PayrollCycle;
use App\Models\PayrollDeduction;
use App\Models\PayrollEarning;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantDatabaseManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\select;

class SssReportSampleDataSeeder extends Seeder
{
    protected ?PayrollCycle $payrollCycle = null;

    protected ?User $systemUser = null;

    /**
     * Run the database seeds.
     *
     * @param  string|null  $tenantSlug  Optional tenant slug for non-interactive mode
     */
    public function run(?string $tenantSlug = null): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->error('No tenants found. Please create a tenant first.');

            return;
        }

        // Use provided slug, or prompt interactively, or fall back to first tenant
        if (! $tenantSlug) {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                try {
                    $tenantSlug = select(
                        label: 'Which tenant do you want to seed SSS report data for?',
                        options: $tenants->pluck('name', 'slug')->toArray(),
                        default: $tenants->first()?->slug,
                    );
                } catch (\Exception) {
                    // Fallback if prompts don't work (non-interactive)
                    $tenantSlug = $tenants->first()?->slug;
                }
            } else {
                $tenantSlug = $tenants->first()?->slug;
            }
        }

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $this->command->info("Seeding SSS report sample data for tenant: {$tenant->name} ({$tenant->slug})");

        // Switch to tenant database
        app(TenantDatabaseManager::class)->switchConnection($tenant);

        // Check if employees exist
        $employeeCount = Employee::whereNotNull('sss_number')->count();
        if ($employeeCount === 0) {
            $this->command->error('No employees with SSS numbers found. Please run TenantSampleDataSeeder first.');

            return;
        }

        $this->command->info("Found {$employeeCount} employees with SSS numbers.");

        $this->seedEmployeeCompensations();
        $this->seedPayrollCycle();
        $this->seedPayrollPeriodsAndEntries();
        $this->seedSssLoansAndPayments();

        $this->command->info('SSS report sample data seeded successfully!');
        $this->command->newLine();
        $this->command->info('You can now test SSS reports at: /reports/sss');
    }

    protected function seedEmployeeCompensations(): void
    {
        $this->command->info('Creating employee compensation records...');

        $employees = Employee::whereNotNull('sss_number')
            ->where('employment_status', 'active')
            ->get();

        $banks = ['BDO', 'BPI', 'Metrobank', 'UnionBank', 'Landbank', 'PNB', 'Security Bank', 'RCBC'];

        foreach ($employees as $employee) {
            // Skip if compensation already exists
            if (EmployeeCompensation::where('employee_id', $employee->id)->exists()) {
                continue;
            }

            // Use the basic_salary from employee record or generate one
            $basicPay = $employee->basic_salary ?? fake()->randomFloat(2, 18000, 150000);

            EmployeeCompensation::create([
                'employee_id' => $employee->id,
                'basic_pay' => $basicPay,
                'currency' => 'PHP',
                'pay_type' => PayType::SemiMonthly,
                'effective_date' => $employee->hire_date ?? now()->subMonths(6),
                'bank_name' => fake()->randomElement($banks),
                'account_name' => $employee->first_name.' '.$employee->last_name,
                'account_number' => fake()->numerify('############'),
                'account_type' => fake()->randomElement([BankAccountType::Savings, BankAccountType::Checking]),
            ]);

            // Update employee basic_salary if not set
            if (! $employee->basic_salary) {
                $employee->update(['basic_salary' => $basicPay]);
            }
        }

        $this->command->info("Created compensation records for {$employees->count()} employees.");
    }

    protected function seedPayrollCycle(): void
    {
        $this->command->info('Creating payroll cycle...');

        $this->payrollCycle = PayrollCycle::firstOrCreate(
            ['code' => 'SEMI-MONTHLY-DEFAULT'],
            [
                'name' => 'Semi-Monthly Payroll',
                'cycle_type' => PayrollCycleType::SemiMonthly,
                'description' => 'Default semi-monthly payroll cycle',
                'status' => 'active',
                'cutoff_rules' => PayrollCycle::getDefaultCutoffRules(PayrollCycleType::SemiMonthly),
                'is_default' => true,
            ]
        );

        $this->systemUser = User::first();
    }

    protected function seedPayrollPeriodsAndEntries(): void
    {
        $this->command->info('Creating payroll periods and entries...');

        // Create periods for Oct 2025 through Jan 2026 (Q4 2025 + Jan 2026)
        $periods = [
            // October 2025
            ['year' => 2025, 'month' => 10, 'period' => 1, 'start' => '2025-10-01', 'end' => '2025-10-15'],
            ['year' => 2025, 'month' => 10, 'period' => 2, 'start' => '2025-10-16', 'end' => '2025-10-31'],
            // November 2025
            ['year' => 2025, 'month' => 11, 'period' => 1, 'start' => '2025-11-01', 'end' => '2025-11-15'],
            ['year' => 2025, 'month' => 11, 'period' => 2, 'start' => '2025-11-16', 'end' => '2025-11-30'],
            // December 2025
            ['year' => 2025, 'month' => 12, 'period' => 1, 'start' => '2025-12-01', 'end' => '2025-12-15'],
            ['year' => 2025, 'month' => 12, 'period' => 2, 'start' => '2025-12-16', 'end' => '2025-12-31'],
            // January 2026
            ['year' => 2026, 'month' => 1, 'period' => 1, 'start' => '2026-01-01', 'end' => '2026-01-15'],
            ['year' => 2026, 'month' => 1, 'period' => 2, 'start' => '2026-01-16', 'end' => '2026-01-31'],
        ];

        $employees = Employee::whereNotNull('sss_number')
            ->where('employment_status', 'active')
            ->get();

        $this->command->info("Creating payroll entries for {$employees->count()} active employees...");

        foreach ($periods as $periodData) {
            $periodNumber = (($periodData['month'] - 1) * 2) + $periodData['period'];
            $monthName = Carbon::create($periodData['year'], $periodData['month'], 1)->format('F');

            $payrollPeriod = PayrollPeriod::firstOrCreate(
                [
                    'payroll_cycle_id' => $this->payrollCycle->id,
                    'year' => $periodData['year'],
                    'cutoff_start' => $periodData['start'],
                    'cutoff_end' => $periodData['end'],
                ],
                [
                    'name' => "{$monthName} {$periodData['year']} - Period {$periodData['period']}",
                    'period_type' => 'regular',
                    'period_number' => $periodNumber,
                    'pay_date' => $periodData['period'] === 1
                        ? Carbon::create($periodData['year'], $periodData['month'], 25)
                        : Carbon::create($periodData['year'], $periodData['month'], 1)->addMonth()->startOfMonth()->addDays(9),
                    'status' => PayrollPeriodStatus::Closed,
                    'employee_count' => $employees->count(),
                    'opened_at' => Carbon::parse($periodData['start'])->subDays(3),
                    'closed_at' => Carbon::parse($periodData['end'])->addDays(5),
                    'closed_by' => $this->systemUser?->id,
                ]
            );

            $this->createPayrollEntries($payrollPeriod, $employees);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    protected function createPayrollEntries(PayrollPeriod $period, $employees): void
    {
        $totalGross = 0;
        $totalDeductions = 0;

        foreach ($employees as $employee) {
            // Skip if entry already exists
            if (PayrollEntry::where('payroll_period_id', $period->id)
                ->where('employee_id', $employee->id)
                ->exists()) {
                continue;
            }

            // Calculate realistic SSS contributions based on salary
            $basicSalary = $employee->basic_salary ?? fake()->randomFloat(2, 18000, 150000);
            $halfMonthSalary = $basicSalary / 2;

            // SSS contribution table (simplified) - based on monthly salary credit
            $sssContribution = $this->calculateSssContribution($basicSalary);
            $sssEmployeeHalf = $sssContribution['employee'] / 2; // Half per cutoff
            $sssEmployerHalf = $sssContribution['employer'] / 2;

            // PhilHealth - 5% of basic salary (2.5% employee, 2.5% employer), max 5000
            $philhealthTotal = min($basicSalary * 0.05, 5000);
            $philhealthEmployeeHalf = ($philhealthTotal / 2) / 2;

            // Pag-IBIG - fixed 200/month (100/cutoff employee)
            $pagibigEmployeeHalf = 100;
            $pagibigEmployerHalf = 100;

            // Withholding tax (simplified calculation)
            $withholdingTax = $this->calculateWithholdingTax($halfMonthSalary);

            // Calculate overtime and allowance amounts
            $overtimePay = fake()->randomFloat(2, 0, 1500);
            $allowancesTotal = fake()->randomFloat(2, 0, 500);
            $grossPay = $halfMonthSalary + $overtimePay + $allowancesTotal;
            $totalDeductionsEntry = $sssEmployeeHalf + $philhealthEmployeeHalf + $pagibigEmployeeHalf + $withholdingTax;
            $netPay = $grossPay - $totalDeductionsEntry;

            $payrollEntry = PayrollEntry::create([
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->last_name.', '.$employee->first_name,
                'department_name' => $employee->department?->name ?? 'Unassigned',
                'position_name' => $employee->position?->title ?? 'Staff',
                'basic_salary_snapshot' => $basicSalary,
                'pay_type_snapshot' => $employee->pay_type ?? 'monthly',
                'days_worked' => fake()->randomFloat(2, 10, 11),
                'total_regular_minutes' => fake()->numberBetween(4800, 5280),
                'total_late_minutes' => fake()->numberBetween(0, 60),
                'total_undertime_minutes' => fake()->numberBetween(0, 30),
                'total_overtime_minutes' => fake()->numberBetween(0, 240),
                'total_night_diff_minutes' => 0,
                'absent_days' => fake()->randomFloat(2, 0, 1),
                'holiday_days' => 0,
                'basic_pay' => $halfMonthSalary,
                'overtime_pay' => $overtimePay,
                'night_diff_pay' => 0,
                'holiday_pay' => 0,
                'allowances_total' => $allowancesTotal,
                'bonuses_total' => 0,
                'gross_pay' => $grossPay,
                'sss_employee' => round($sssEmployeeHalf, 2),
                'sss_employer' => round($sssEmployerHalf, 2),
                'philhealth_employee' => round($philhealthEmployeeHalf, 2),
                'philhealth_employer' => round($philhealthEmployeeHalf, 2),
                'pagibig_employee' => $pagibigEmployeeHalf,
                'pagibig_employer' => $pagibigEmployerHalf,
                'withholding_tax' => round($withholdingTax, 2),
                'other_deductions_total' => 0,
                'total_deductions' => round($totalDeductionsEntry, 2),
                'total_employer_contributions' => round($sssEmployerHalf + $philhealthEmployeeHalf + $pagibigEmployerHalf, 2),
                'net_pay' => round($netPay, 2),
                'status' => PayrollEntryStatus::Approved,
                'computed_at' => Carbon::parse($period->cutoff_end)->addDay(),
                'approved_at' => Carbon::parse($period->cutoff_end)->addDays(3),
            ]);

            // Create earnings line items
            $this->createEarnings($payrollEntry, $halfMonthSalary, $overtimePay, $allowancesTotal);

            // Create deductions line items
            $this->createDeductions($payrollEntry, $basicSalary, $sssEmployeeHalf, $sssEmployerHalf, $philhealthEmployeeHalf, $pagibigEmployeeHalf, $pagibigEmployerHalf, $withholdingTax);

            $totalGross += $grossPay;
            $totalDeductions += $totalDeductionsEntry;
        }

        // Update period totals
        $period->update([
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalGross - $totalDeductions,
        ]);
    }

    /**
     * Calculate SSS contribution based on monthly salary credit.
     *
     * @return array{employee: float, employer: float}
     */
    protected function calculateSssContribution(float $monthlySalary): array
    {
        // 2024 SSS contribution table (simplified)
        // Employee: 4.5% of MSC, Employer: 9.5% of MSC
        // MSC ranges from 4,000 to 30,000

        $msc = min(max($monthlySalary, 4000), 30000);

        return [
            'employee' => $msc * 0.045,
            'employer' => $msc * 0.095,
        ];
    }

    /**
     * Calculate withholding tax (simplified BIR tax table).
     */
    protected function calculateWithholdingTax(float $taxableIncome): float
    {
        // Simplified 2023 tax table for semi-monthly
        if ($taxableIncome <= 10417) {
            return 0;
        } elseif ($taxableIncome <= 16667) {
            return ($taxableIncome - 10417) * 0.15;
        } elseif ($taxableIncome <= 33333) {
            return 937.50 + (($taxableIncome - 16667) * 0.20);
        } elseif ($taxableIncome <= 83333) {
            return 4270.70 + (($taxableIncome - 33333) * 0.25);
        } elseif ($taxableIncome <= 333333) {
            return 16770.70 + (($taxableIncome - 83333) * 0.30);
        } else {
            return 91770.70 + (($taxableIncome - 333333) * 0.35);
        }
    }

    protected function seedSssLoansAndPayments(): void
    {
        $this->command->info('Creating SSS loans and payments...');

        $employees = Employee::whereNotNull('sss_number')
            ->where('employment_status', 'active')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        $sssLoanTypes = [
            LoanType::SssSalary,
            LoanType::SssCalamity,
            LoanType::SssEducational,
            LoanType::SssEmergency,
            LoanType::SssStockInvestment,
        ];

        $loansCreated = 0;
        $paymentsCreated = 0;

        foreach ($employees as $employee) {
            // Randomly assign 1-2 SSS loan types per employee
            $loanCount = fake()->numberBetween(1, 2);
            $selectedTypes = fake()->randomElements($sssLoanTypes, $loanCount);

            foreach ($selectedTypes as $loanType) {
                // Skip if loan already exists
                if (EmployeeLoan::where('employee_id', $employee->id)
                    ->where('loan_type', $loanType)
                    ->where('status', LoanStatus::Active)
                    ->exists()) {
                    continue;
                }

                $loan = $this->createSssLoan($employee, $loanType);
                $loansCreated++;

                // Create payments for Q4 2025 and Q1 2026
                $paymentDates = [
                    '2025-10-15', '2025-10-31',
                    '2025-11-15', '2025-11-30',
                    '2025-12-15', '2025-12-31',
                    '2026-01-15', '2026-01-31',
                ];

                $remainingBalance = $loan->total_amount;

                foreach ($paymentDates as $paymentDate) {
                    if ($remainingBalance <= 0) {
                        break;
                    }

                    $paymentAmount = min($loan->monthly_deduction / 2, $remainingBalance);
                    $balanceBefore = $remainingBalance;
                    $remainingBalance -= $paymentAmount;

                    LoanPayment::create([
                        'employee_loan_id' => $loan->id,
                        'amount' => round($paymentAmount, 2),
                        'balance_before' => round($balanceBefore, 2),
                        'balance_after' => round(max(0, $remainingBalance), 2),
                        'payment_date' => $paymentDate,
                        'payment_source' => 'payroll',
                        'notes' => null,
                    ]);
                    $paymentsCreated++;
                }

                // Update loan totals
                $totalPaid = $loan->total_amount - max(0, $remainingBalance);
                $loan->update([
                    'total_paid' => round($totalPaid, 2),
                    'remaining_balance' => round(max(0, $remainingBalance), 2),
                    'status' => $remainingBalance <= 0 ? LoanStatus::Completed : LoanStatus::Active,
                    'actual_end_date' => $remainingBalance <= 0 ? now() : null,
                ]);
            }
        }

        $this->command->info("Created {$loansCreated} SSS loans with {$paymentsCreated} payments.");
    }

    protected function createSssLoan(Employee $employee, LoanType $loanType): EmployeeLoan
    {
        // Realistic loan amounts by type
        $loanParams = match ($loanType) {
            LoanType::SssSalary => [
                'principal' => fake()->randomFloat(2, 20000, 80000),
                'interest' => 0.10,
                'term' => fake()->randomElement([12, 24]),
            ],
            LoanType::SssCalamity => [
                'principal' => fake()->randomFloat(2, 10000, 40000),
                'interest' => 0.10,
                'term' => fake()->randomElement([12, 24]),
            ],
            LoanType::SssEducational => [
                'principal' => fake()->randomFloat(2, 15000, 50000),
                'interest' => 0.10,
                'term' => fake()->randomElement([12, 24]),
            ],
            LoanType::SssEmergency => [
                'principal' => fake()->randomFloat(2, 5000, 20000),
                'interest' => 0.10,
                'term' => 12,
            ],
            LoanType::SssStockInvestment => [
                'principal' => fake()->randomFloat(2, 10000, 30000),
                'interest' => 0.10,
                'term' => 24,
            ],
            default => [
                'principal' => fake()->randomFloat(2, 10000, 50000),
                'interest' => 0.10,
                'term' => 12,
            ],
        };

        $totalAmount = $loanParams['principal'] * (1 + ($loanParams['interest'] * ($loanParams['term'] / 12)));
        $monthlyDeduction = $totalAmount / $loanParams['term'];
        $startDate = fake()->dateTimeBetween('2025-06-01', '2025-09-30');

        return EmployeeLoan::create([
            'employee_id' => $employee->id,
            'loan_type' => $loanType,
            'loan_code' => strtoupper('SSS-'.fake()->unique()->lexify('????-????')),
            'reference_number' => 'SSS-'.fake()->numerify('####-####-####'),
            'principal_amount' => $loanParams['principal'],
            'interest_rate' => $loanParams['interest'],
            'monthly_deduction' => round($monthlyDeduction, 2),
            'term_months' => $loanParams['term'],
            'total_amount' => round($totalAmount, 2),
            'total_paid' => 0,
            'remaining_balance' => round($totalAmount, 2),
            'start_date' => $startDate,
            'expected_end_date' => (clone $startDate)->modify("+{$loanParams['term']} months"),
            'actual_end_date' => null,
            'status' => LoanStatus::Active,
            'notes' => null,
            'metadata' => null,
            'created_by' => $this->systemUser?->id,
        ]);
    }

    protected function createEarnings(
        PayrollEntry $entry,
        float $basicPay,
        float $overtimePay,
        float $allowancesTotal
    ): void {
        // Basic Pay
        PayrollEarning::create([
            'payroll_entry_id' => $entry->id,
            'earning_type' => EarningType::BasicPay,
            'earning_code' => 'BASIC',
            'description' => 'Basic Pay (Semi-monthly)',
            'quantity' => 1,
            'quantity_unit' => 'cutoff',
            'rate' => $basicPay,
            'multiplier' => 1.00,
            'amount' => $basicPay,
            'is_taxable' => true,
        ]);

        // Overtime Pay (if any)
        if ($overtimePay > 0) {
            $otHours = fake()->randomFloat(2, 1, 10);
            $otRate = $overtimePay / $otHours;
            PayrollEarning::create([
                'payroll_entry_id' => $entry->id,
                'earning_type' => EarningType::Overtime,
                'earning_code' => 'OT-REG',
                'description' => 'Overtime (Regular)',
                'quantity' => $otHours,
                'quantity_unit' => 'hours',
                'rate' => round($otRate, 2),
                'multiplier' => 1.25,
                'amount' => $overtimePay,
                'is_taxable' => true,
            ]);
        }

        // Allowance (if any)
        if ($allowancesTotal > 0) {
            PayrollEarning::create([
                'payroll_entry_id' => $entry->id,
                'earning_type' => EarningType::Allowance,
                'earning_code' => 'MEAL',
                'description' => 'Meal Allowance',
                'quantity' => 1,
                'quantity_unit' => 'cutoff',
                'rate' => $allowancesTotal,
                'multiplier' => 1.00,
                'amount' => $allowancesTotal,
                'is_taxable' => false,
            ]);
        }
    }

    protected function createDeductions(
        PayrollEntry $entry,
        float $basicSalary,
        float $sssEmployee,
        float $sssEmployer,
        float $philhealthEmployee,
        float $pagibigEmployee,
        float $pagibigEmployer,
        float $withholdingTax
    ): void {
        // SSS Employee Share
        PayrollDeduction::create([
            'payroll_entry_id' => $entry->id,
            'deduction_type' => DeductionType::Sss,
            'deduction_code' => 'SSS-EE',
            'description' => 'SSS Contribution (Employee)',
            'basis_amount' => $basicSalary,
            'rate' => 0.045,
            'amount' => round($sssEmployee, 2),
            'is_employee_share' => true,
            'is_employer_share' => false,
        ]);

        // SSS Employer Share
        PayrollDeduction::create([
            'payroll_entry_id' => $entry->id,
            'deduction_type' => DeductionType::Sss,
            'deduction_code' => 'SSS-ER',
            'description' => 'SSS Contribution (Employer)',
            'basis_amount' => $basicSalary,
            'rate' => 0.095,
            'amount' => round($sssEmployer, 2),
            'is_employee_share' => false,
            'is_employer_share' => true,
        ]);

        // PhilHealth Employee Share
        PayrollDeduction::create([
            'payroll_entry_id' => $entry->id,
            'deduction_type' => DeductionType::Philhealth,
            'deduction_code' => 'PH-EE',
            'description' => 'PhilHealth Contribution (Employee)',
            'basis_amount' => $basicSalary,
            'rate' => 0.025,
            'amount' => round($philhealthEmployee, 2),
            'is_employee_share' => true,
            'is_employer_share' => false,
        ]);

        // PhilHealth Employer Share
        PayrollDeduction::create([
            'payroll_entry_id' => $entry->id,
            'deduction_type' => DeductionType::Philhealth,
            'deduction_code' => 'PH-ER',
            'description' => 'PhilHealth Contribution (Employer)',
            'basis_amount' => $basicSalary,
            'rate' => 0.025,
            'amount' => round($philhealthEmployee, 2),
            'is_employee_share' => false,
            'is_employer_share' => true,
        ]);

        // Pag-IBIG Employee Share
        PayrollDeduction::create([
            'payroll_entry_id' => $entry->id,
            'deduction_type' => DeductionType::Pagibig,
            'deduction_code' => 'HDMF-EE',
            'description' => 'Pag-IBIG Contribution (Employee)',
            'basis_amount' => $basicSalary,
            'rate' => 0.02,
            'amount' => round($pagibigEmployee, 2),
            'is_employee_share' => true,
            'is_employer_share' => false,
        ]);

        // Pag-IBIG Employer Share
        PayrollDeduction::create([
            'payroll_entry_id' => $entry->id,
            'deduction_type' => DeductionType::Pagibig,
            'deduction_code' => 'HDMF-ER',
            'description' => 'Pag-IBIG Contribution (Employer)',
            'basis_amount' => $basicSalary,
            'rate' => 0.02,
            'amount' => round($pagibigEmployer, 2),
            'is_employee_share' => false,
            'is_employer_share' => true,
        ]);

        // Withholding Tax
        if ($withholdingTax > 0) {
            PayrollDeduction::create([
                'payroll_entry_id' => $entry->id,
                'deduction_type' => DeductionType::WithholdingTax,
                'deduction_code' => 'TAX',
                'description' => 'Withholding Tax',
                'basis_amount' => $entry->gross_pay - $sssEmployee - $philhealthEmployee - $pagibigEmployee,
                'rate' => 0,
                'amount' => round($withholdingTax, 2),
                'is_employee_share' => true,
                'is_employer_share' => false,
            ]);
        }
    }
}
