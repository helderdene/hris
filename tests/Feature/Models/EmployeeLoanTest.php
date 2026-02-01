<?php

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use App\Models\Tenant;
use App\Models\User;
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

describe('EmployeeLoan Model', function () {
    it('can be created with factory', function () {
        $loan = EmployeeLoan::factory()->create();

        expect($loan)->toBeInstanceOf(EmployeeLoan::class);
        expect($loan->id)->toBeGreaterThan(0);
    });

    it('has correct casts', function () {
        $loan = EmployeeLoan::factory()->create([
            'loan_type' => LoanType::SssSalary,
            'status' => LoanStatus::Active,
            'principal_amount' => 50000,
            'start_date' => '2026-01-01',
        ]);

        expect($loan->loan_type)->toBeInstanceOf(LoanType::class);
        expect($loan->status)->toBeInstanceOf(LoanStatus::class);
        expect($loan->principal_amount)->toBeString();
        expect($loan->start_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('belongs to an employee', function () {
        $employee = Employee::factory()->create();
        $loan = EmployeeLoan::factory()->forEmployee($employee)->create();

        expect($loan->employee)->toBeInstanceOf(Employee::class);
        expect($loan->employee->id)->toBe($employee->id);
    });

    it('has many payments', function () {
        $loan = EmployeeLoan::factory()->create();
        $loan->payments()->create([
            'amount' => 1000,
            'balance_before' => 50000,
            'balance_after' => 49000,
            'payment_date' => now(),
            'payment_source' => 'manual',
        ]);

        expect($loan->payments)->toHaveCount(1);
        expect($loan->payments->first())->toBeInstanceOf(LoanPayment::class);
    });

    it('belongs to created by user', function () {
        $user = User::factory()->create();
        $loan = EmployeeLoan::factory()->create(['created_by' => $user->id]);

        expect($loan->createdBy)->toBeInstanceOf(User::class);
        expect($loan->createdBy->id)->toBe($user->id);
    });
});

describe('EmployeeLoan Scopes', function () {
    it('filters active loans', function () {
        EmployeeLoan::factory()->create(['status' => LoanStatus::Active]);
        EmployeeLoan::factory()->create(['status' => LoanStatus::Completed]);
        EmployeeLoan::factory()->create(['status' => LoanStatus::OnHold]);

        $activeLoans = EmployeeLoan::active()->get();

        expect($activeLoans)->toHaveCount(1);
        expect($activeLoans->first()->status)->toBe(LoanStatus::Active);
    });

    it('filters deductible loans', function () {
        EmployeeLoan::factory()->create([
            'status' => LoanStatus::Active,
            'remaining_balance' => 10000,
        ]);
        EmployeeLoan::factory()->create([
            'status' => LoanStatus::Active,
            'remaining_balance' => 0,
        ]);
        EmployeeLoan::factory()->create([
            'status' => LoanStatus::OnHold,
            'remaining_balance' => 10000,
        ]);

        $deductibleLoans = EmployeeLoan::deductible()->get();

        expect($deductibleLoans)->toHaveCount(1);
    });

    it('filters loans for employee', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee1)->count(2)->create();
        EmployeeLoan::factory()->forEmployee($employee2)->create();

        $loans = EmployeeLoan::forEmployee($employee1)->get();

        expect($loans)->toHaveCount(2);
    });

    it('filters by loan type', function () {
        EmployeeLoan::factory()->sssSalary()->create();
        EmployeeLoan::factory()->pagibigMpl()->create();
        EmployeeLoan::factory()->companyCashAdvance()->create();

        $sssLoans = EmployeeLoan::ofType(LoanType::SssSalary)->get();

        expect($sssLoans)->toHaveCount(1);
    });

    it('filters government loans', function () {
        EmployeeLoan::factory()->sssSalary()->create();
        EmployeeLoan::factory()->pagibigMpl()->create();
        EmployeeLoan::factory()->companyCashAdvance()->create();

        $govtLoans = EmployeeLoan::governmentLoans()->get();

        expect($govtLoans)->toHaveCount(2);
    });

    it('filters company loans', function () {
        EmployeeLoan::factory()->sssSalary()->create();
        EmployeeLoan::factory()->companyCashAdvance()->create();
        EmployeeLoan::factory()->companyEmergency()->create();

        $companyLoans = EmployeeLoan::companyLoans()->get();

        expect($companyLoans)->toHaveCount(2);
    });
});

describe('EmployeeLoan Balance Tracking', function () {
    it('records payment and updates balance', function () {
        $loan = EmployeeLoan::factory()->create([
            'total_amount' => 10000,
            'total_paid' => 0,
            'remaining_balance' => 10000,
        ]);

        $payment = $loan->recordPayment(
            amount: 2000,
            paymentDate: now()->toDateString(),
            paymentSource: 'manual'
        );

        expect($payment)->toBeInstanceOf(LoanPayment::class);
        expect($payment->amount)->toBe('2000.00');
        expect($payment->balance_before)->toBe('10000.00');
        expect($payment->balance_after)->toBe('8000.00');

        $loan->refresh();
        expect($loan->total_paid)->toBe('2000.00');
        expect($loan->remaining_balance)->toBe('8000.00');
    });

    it('marks loan as completed when balance reaches zero', function () {
        $loan = EmployeeLoan::factory()->create([
            'total_amount' => 1000,
            'total_paid' => 0,
            'remaining_balance' => 1000,
            'status' => LoanStatus::Active,
        ]);

        $loan->recordPayment(
            amount: 1000,
            paymentDate: now()->toDateString(),
            paymentSource: 'manual'
        );

        $loan->refresh();
        expect($loan->status)->toBe(LoanStatus::Completed);
        expect($loan->remaining_balance)->toBe('0.00');
        expect($loan->actual_end_date)->not->toBeNull();
    });

    it('calculates correct deduction amount', function () {
        $loan = EmployeeLoan::factory()->create([
            'monthly_deduction' => 5000,
            'remaining_balance' => 10000,
        ]);

        expect($loan->getDeductionAmount())->toBe(5000.0);

        $loan->remaining_balance = 3000;
        expect($loan->getDeductionAmount())->toBe(3000.0);
    });

    it('calculates progress percentage', function () {
        $loan = EmployeeLoan::factory()->create([
            'total_amount' => 10000,
            'total_paid' => 2500,
        ]);

        expect($loan->getProgressPercentage())->toBe(25.0);
    });
});

describe('EmployeeLoan Status Transitions', function () {
    it('can be put on hold', function () {
        $loan = EmployeeLoan::factory()->active()->create();

        $loan->putOnHold('Taking a break');

        expect($loan->status)->toBe(LoanStatus::OnHold);
        expect($loan->metadata['on_hold_reason'])->toBe('Taking a break');
    });

    it('can be resumed from on hold', function () {
        $loan = EmployeeLoan::factory()->onHold()->create();

        $loan->resume();

        expect($loan->status)->toBe(LoanStatus::Active);
        expect($loan->metadata['resumed_at'])->not->toBeNull();
    });

    it('can be cancelled', function () {
        $loan = EmployeeLoan::factory()->active()->create();

        $loan->cancel('Employee resigned');

        expect($loan->status)->toBe(LoanStatus::Cancelled);
        expect($loan->metadata['cancellation_reason'])->toBe('Employee resigned');
    });

    it('can be marked as completed', function () {
        $loan = EmployeeLoan::factory()->active()->create([
            'actual_end_date' => null,
        ]);

        $loan->markAsCompleted();

        expect($loan->status)->toBe(LoanStatus::Completed);
        expect($loan->actual_end_date)->not->toBeNull();
    });
});

describe('EmployeeLoan Helper Methods', function () {
    it('identifies government loans', function () {
        $sssLoan = EmployeeLoan::factory()->sssSalary()->create();
        $companyLoan = EmployeeLoan::factory()->companyCashAdvance()->create();

        expect($sssLoan->isGovernmentLoan())->toBeTrue();
        expect($sssLoan->isCompanyLoan())->toBeFalse();

        expect($companyLoan->isGovernmentLoan())->toBeFalse();
        expect($companyLoan->isCompanyLoan())->toBeTrue();
    });
});
