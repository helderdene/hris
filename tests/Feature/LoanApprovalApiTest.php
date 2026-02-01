<?php

use App\Enums\EmploymentStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantForLoanApproval(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createHrUserForLoanApproval(Tenant $tenant): array
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'employment_status' => EmploymentStatus::Active,
    ]);

    return [$user, $employee];
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Loan Approval', function () {
    it('approves a pending application and creates employee loan', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApproval($tenant);

        [$hrUser, $hrEmployee] = createHrUserForLoanApproval($tenant);
        $this->actingAs($hrUser);

        $application = LoanApplication::factory()->pending()->create([
            'loan_type' => LoanType::SssSalary,
            'amount_requested' => 50000,
            'term_months' => 24,
        ]);

        $service = app(LoanApplicationService::class);
        $result = $service->approve($application, $hrEmployee, [
            'interest_rate' => 0.10,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'remarks' => 'Approved for employee.',
        ]);

        expect($result->status)->toBe(LoanApplicationStatus::Approved);
        expect($result->reviewer_employee_id)->toBe($hrEmployee->id);
        expect($result->reviewer_remarks)->toBe('Approved for employee.');
        expect($result->reviewed_at)->not->toBeNull();
        expect($result->employee_loan_id)->not->toBeNull();

        // Verify EmployeeLoan was created correctly
        $loan = EmployeeLoan::find($result->employee_loan_id);
        expect($loan)->not->toBeNull();
        expect($loan->employee_id)->toBe($application->employee_id);
        expect($loan->loan_type)->toBe(LoanType::SssSalary);
        expect((float) $loan->principal_amount)->toBe(50000.0);
        expect($loan->term_months)->toBe(24);
        expect($loan->status)->toBe(LoanStatus::Active);

        // Verify calculation: total = 50000 + (50000 * 0.10 * 24/12) = 60000
        expect((float) $loan->total_amount)->toBe(60000.0);
        // Monthly deduction = 60000 / 24 = 2500
        expect((float) $loan->monthly_deduction)->toBe(2500.0);
        expect((float) $loan->remaining_balance)->toBe(60000.0);
    });

    it('rejects a pending application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApproval($tenant);

        [$hrUser, $hrEmployee] = createHrUserForLoanApproval($tenant);
        $this->actingAs($hrUser);

        $application = LoanApplication::factory()->pending()->create();

        $service = app(LoanApplicationService::class);
        $result = $service->reject($application, $hrEmployee, 'Insufficient documentation.');

        expect($result->status)->toBe(LoanApplicationStatus::Rejected);
        expect($result->reviewer_employee_id)->toBe($hrEmployee->id);
        expect($result->reviewer_remarks)->toBe('Insufficient documentation.');
        expect($result->reviewed_at)->not->toBeNull();
        expect($result->employee_loan_id)->toBeNull();
    });

    it('cannot approve a non-pending application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApproval($tenant);

        [$hrUser, $hrEmployee] = createHrUserForLoanApproval($tenant);
        $this->actingAs($hrUser);

        $application = LoanApplication::factory()->draft()->create();

        $service = app(LoanApplicationService::class);

        expect(fn () => $service->approve($application, $hrEmployee, [
            'interest_rate' => 0.10,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('cannot reject a non-pending application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApproval($tenant);

        [$hrUser, $hrEmployee] = createHrUserForLoanApproval($tenant);
        $this->actingAs($hrUser);

        $application = LoanApplication::factory()->approved()->create();

        $service = app(LoanApplicationService::class);

        expect(fn () => $service->reject($application, $hrEmployee, 'Reason'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('creates loan with correct calculations for zero interest', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApproval($tenant);

        [$hrUser, $hrEmployee] = createHrUserForLoanApproval($tenant);
        $this->actingAs($hrUser);

        $application = LoanApplication::factory()->pending()->create([
            'loan_type' => LoanType::CompanyCashAdvance,
            'amount_requested' => 10000,
            'term_months' => 5,
        ]);

        $service = app(LoanApplicationService::class);
        $result = $service->approve($application, $hrEmployee, [
            'interest_rate' => 0,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $loan = EmployeeLoan::find($result->employee_loan_id);
        expect((float) $loan->total_amount)->toBe(10000.0);
        expect((float) $loan->monthly_deduction)->toBe(2000.0);
    });
});
