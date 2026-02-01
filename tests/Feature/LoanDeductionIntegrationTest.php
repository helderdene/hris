<?php

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Services\Payroll\LoanDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $tenant = Tenant::factory()->create();
    app()->instance('tenant', $tenant);
});

describe('LoanDeductionService', function () {
    it('gets deductible loans for employee', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'remaining_balance' => 10000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'remaining_balance' => 5000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->onHold()->create();
        EmployeeLoan::factory()->forEmployee($employee)->completed()->create();

        $service = new LoanDeductionService;
        $loans = $service->getDeductibleLoans($employee);

        expect($loans)->toHaveCount(2);
    });

    it('calculates loan deductions only on second cutoff for semi-monthly', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'monthly_deduction' => 2000,
            'remaining_balance' => 10000,
        ]);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();

        $firstCutoffPeriod = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 1,
        ]);

        $secondCutoffPeriod = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 2,
        ]);

        $service = new LoanDeductionService;

        $lineItemsFirst = collect();
        $totalFirst = $service->calculateLoanDeductions($employee, $firstCutoffPeriod, $lineItemsFirst);

        expect($totalFirst)->toBe(0.0);
        expect($lineItemsFirst)->toBeEmpty();

        $lineItemsSecond = collect();
        $totalSecond = $service->calculateLoanDeductions($employee, $secondCutoffPeriod, $lineItemsSecond);

        expect($totalSecond)->toBe(2000.0);
        expect($lineItemsSecond)->toHaveCount(1);
    });

    it('calculates loan deductions on every period for monthly payroll', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'monthly_deduction' => 3000,
            'remaining_balance' => 30000,
        ]);

        $cycle = PayrollCycle::factory()->monthly()->create();

        $period = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 1,
        ]);

        $service = new LoanDeductionService;
        $lineItems = collect();
        $total = $service->calculateLoanDeductions($employee, $period, $lineItems);

        expect($total)->toBe(3000.0);
        expect($lineItems)->toHaveCount(1);
    });

    it('calculates correct deduction when balance is less than monthly amount', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'monthly_deduction' => 5000,
            'remaining_balance' => 3000,
        ]);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();

        $period = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 2,
        ]);

        $service = new LoanDeductionService;
        $lineItems = collect();
        $total = $service->calculateLoanDeductions($employee, $period, $lineItems);

        expect($total)->toBe(3000.0);
    });

    it('calculates total deductions from multiple loans', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->sssSalary()->active()->create([
            'monthly_deduction' => 2000,
            'remaining_balance' => 20000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->pagibigMpl()->active()->create([
            'monthly_deduction' => 1500,
            'remaining_balance' => 15000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->companyCashAdvance()->active()->create([
            'monthly_deduction' => 500,
            'remaining_balance' => 3000,
        ]);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();

        $period = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 2,
        ]);

        $service = new LoanDeductionService;
        $lineItems = collect();
        $total = $service->calculateLoanDeductions($employee, $period, $lineItems);

        expect($total)->toBe(4000.0);
        expect($lineItems)->toHaveCount(3);
    });

    it('returns zero for employees with no loans', function () {
        $employee = Employee::factory()->create();

        $cycle = PayrollCycle::factory()->semiMonthly()->create();

        $period = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 2,
        ]);

        $service = new LoanDeductionService;
        $lineItems = collect();
        $total = $service->calculateLoanDeductions($employee, $period, $lineItems);

        expect($total)->toBe(0.0);
        expect($lineItems)->toBeEmpty();
    });

    it('skips on-hold loans', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'monthly_deduction' => 2000,
            'remaining_balance' => 10000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->onHold()->create([
            'monthly_deduction' => 1500,
            'remaining_balance' => 8000,
        ]);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();

        $period = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 2,
        ]);

        $service = new LoanDeductionService;
        $lineItems = collect();
        $total = $service->calculateLoanDeductions($employee, $period, $lineItems);

        expect($total)->toBe(2000.0);
        expect($lineItems)->toHaveCount(1);
    });

    it('includes loan details in line items', function () {
        $employee = Employee::factory()->create();

        $loan = EmployeeLoan::factory()->forEmployee($employee)->sssSalary()->active()->create([
            'loan_code' => 'SSS-TEST-001',
            'reference_number' => 'REF123',
            'monthly_deduction' => 2000,
            'remaining_balance' => 10000,
        ]);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();

        $period = PayrollPeriod::factory()->create([
            'payroll_cycle_id' => $cycle->id,
            'period_number' => 2,
        ]);

        $service = new LoanDeductionService;
        $lineItems = collect();
        $service->calculateLoanDeductions($employee, $period, $lineItems);

        $item = $lineItems->first();

        expect($item['loan_id'])->toBe($loan->id);
        expect($item['loan_type'])->toBe('sss_salary');
        expect($item['loan_code'])->toBe('SSS-TEST-001');
        expect($item['amount'])->toBe(2000.0);
        expect($item['description'])->toContain('SSS Salary Loan');
        expect($item['description'])->toContain('REF123');
    });

    it('calculates total deduction amount helper', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'monthly_deduction' => 2000,
            'remaining_balance' => 10000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->active()->create([
            'monthly_deduction' => 1500,
            'remaining_balance' => 8000,
        ]);

        $service = new LoanDeductionService;
        $total = $service->getTotalDeductionAmount($employee);

        expect($total)->toBe(3500.0);
    });

    it('returns deduction summary by category', function () {
        $employee = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee)->sssSalary()->active()->create([
            'monthly_deduction' => 2000,
            'remaining_balance' => 20000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->sssCalamity()->active()->create([
            'monthly_deduction' => 1000,
            'remaining_balance' => 5000,
        ]);
        EmployeeLoan::factory()->forEmployee($employee)->pagibigMpl()->active()->create([
            'monthly_deduction' => 1500,
            'remaining_balance' => 15000,
        ]);

        $service = new LoanDeductionService;
        $summary = $service->getDeductionSummaryByCategory($employee);

        expect($summary)->toHaveKeys(['SSS', 'Pag-IBIG']);
        expect($summary['SSS']['loans'])->toHaveCount(2);
        expect($summary['SSS']['total'])->toBe(3000.0);
        expect($summary['Pag-IBIG']['loans'])->toHaveCount(1);
        expect($summary['Pag-IBIG']['total'])->toBe(1500.0);
    });
});
