<?php

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApprovalDecision;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanType;
use App\Models\Employee;
use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use App\Models\Tenant;
use App\Services\LoanApplicationService;
use App\Services\LoanApprovalChainResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindLoanChainTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('LoanApprovalChainResolver', function () {
    it('resolves a 3-step chain with standard SLA deadlines', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        $cfo = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
        ]);
        $admin = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_admin_manager' => true,
        ]);
        $releasing = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();

        $application = LoanApplication::factory()->create([
            'employee_id' => $applicant->id,
            'urgency_level' => 3,
            'submitted_at' => now(),
        ]);

        $resolver = new LoanApprovalChainResolver;
        $chain = $resolver->resolveChain($application);

        expect($chain)->toHaveCount(3);
        expect($chain[0]['type'])->toBe('cfo');
        expect($chain[0]['employee']->id)->toBe($cfo->id);
        expect($chain[1]['type'])->toBe('admin_manager');
        expect($chain[1]['employee']->id)->toBe($admin->id);
        expect($chain[2]['type'])->toBe('releasing_officer');
        expect($chain[2]['employee']->id)->toBe($releasing->id);

        // Standard SLA: 5 days cumulative for CFO, 8 for Admin, 10 for Releasing
        $diff1 = $application->submitted_at->diffInRealMinutes($chain[0]['deadline']);
        $diff3 = $application->submitted_at->diffInRealMinutes($chain[2]['deadline']);
        expect($diff1)->toBeGreaterThan(5 * 24 * 60 - 60);
        expect($diff3)->toBeGreaterThan(10 * 24 * 60 - 60);
    });

    it('compresses deadlines to 1.5/1/0.5 days when urgency_level is 5', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_admin_manager' => true,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();
        $application = LoanApplication::factory()->create([
            'employee_id' => $applicant->id,
            'urgency_level' => 5,
            'submitted_at' => now(),
        ]);

        $resolver = new LoanApprovalChainResolver;
        $chain = $resolver->resolveChain($application);

        // Total = 3 days
        $totalMinutes = $application->submitted_at->diffInRealMinutes($chain[2]['deadline']);
        expect($totalMinutes)->toBeGreaterThan(3 * 24 * 60 - 60);
        expect($totalMinutes)->toBeLessThan(3 * 24 * 60 + 60);
    });

    it('skips a missing role and re-numbers the remaining levels', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        // Only CFO and Releasing — no Admin Manager
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();
        $application = LoanApplication::factory()->create([
            'employee_id' => $applicant->id,
            'urgency_level' => 2,
            'submitted_at' => now(),
        ]);

        $resolver = new LoanApprovalChainResolver;
        $chain = $resolver->resolveChain($application);

        expect($chain)->toHaveCount(2);
        expect($chain[0]['level'])->toBe(1);
        expect($chain[0]['type'])->toBe('cfo');
        expect($chain[1]['level'])->toBe(2);
        expect($chain[1]['type'])->toBe('releasing_officer');
    });

    it('collapses to a single step when one employee holds all 3 roles', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        $oneApprover = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
            'is_loan_admin_manager' => true,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();
        $application = LoanApplication::factory()->create([
            'employee_id' => $applicant->id,
            'urgency_level' => 3,
            'submitted_at' => now(),
        ]);

        $resolver = new LoanApprovalChainResolver;
        $chain = $resolver->resolveChain($application);

        expect($chain)->toHaveCount(1);
        expect($chain[0]['employee']->id)->toBe($oneApprover->id);
    });
});

describe('LoanApplicationService chain', function () {
    it('creates approval rows on submit and notifies the level-1 approver', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        \Illuminate\Support\Facades\Notification::fake();

        $cfo = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
        ]);
        $cfoUser = \App\Models\User::factory()->create();
        $cfo->update(['user_id' => $cfoUser->id]);

        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_admin_manager' => true,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();
        $application = LoanApplication::factory()->draft()->create([
            'employee_id' => $applicant->id,
            'urgency_level' => 3,
            'amount_requested' => 5000,
            'term_months' => 6,
        ]);

        $service = new LoanApplicationService(new LoanApprovalChainResolver);
        $submitted = $service->submit($application);

        expect($submitted->status)->toBe(LoanApplicationStatus::Pending);
        expect($submitted->total_approval_levels)->toBe(3);
        expect($submitted->current_approval_level)->toBe(1);
        expect($submitted->approvals)->toHaveCount(3);
        expect($submitted->sla_deadline_at)->not->toBeNull();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $cfoUser,
            \App\Notifications\LoanApplicationSubmittedToApprover::class
        );
    });

    it('advances the chain when a non-final approver approves', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        \Illuminate\Support\Facades\Notification::fake();

        $cfo = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
        ]);
        $cfo->update(['user_id' => \App\Models\User::factory()->create()->id]);

        $admin = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_admin_manager' => true,
        ]);
        $adminUser = \App\Models\User::factory()->create();
        $admin->update(['user_id' => $adminUser->id]);

        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();
        $application = LoanApplication::factory()->draft()->create([
            'employee_id' => $applicant->id,
            'urgency_level' => 3,
            'amount_requested' => 5000,
            'term_months' => 6,
        ]);

        $service = new LoanApplicationService(new LoanApprovalChainResolver);
        $service->submit($application);
        $application->refresh();

        $approved = $service->approve($application, $cfo, ['remarks' => 'OK']);

        expect($approved->current_approval_level)->toBe(2);
        expect($approved->status)->toBe(LoanApplicationStatus::Pending);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $adminUser,
            \App\Notifications\LoanApplicationSubmittedToApprover::class
        );
    });

    it('finalizes and creates an EmployeeLoan on the final-level approval', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        \Illuminate\Support\Facades\Notification::fake();

        $cfo = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
        ]);
        $admin = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_admin_manager' => true,
        ]);
        $releasing = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();
        $applicantUser = \App\Models\User::factory()->create();
        $applicant->update(['user_id' => $applicantUser->id]);

        $application = LoanApplication::factory()->draft()->create([
            'employee_id' => $applicant->id,
            'loan_type' => LoanType::CompanyCashAdvance->value,
            'urgency_level' => 3,
            'amount_requested' => 6000,
            'term_months' => 6,
        ]);

        $service = new LoanApplicationService(new LoanApprovalChainResolver);
        $service->submit($application);

        $service->approve($application->refresh(), $cfo, ['remarks' => 'L1 ok']);
        $service->approve($application->refresh(), $admin, ['remarks' => 'L2 ok']);

        $final = $service->approve($application->refresh(), $releasing, [
            'interest_rate' => 0.10,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'remarks' => 'Released',
        ]);

        expect($final->status)->toBe(LoanApplicationStatus::Approved);
        expect($final->employee_loan_id)->not->toBeNull();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $applicantUser,
            \App\Notifications\LoanApplicationApproved::class
        );
    });

    it('rejects at any level and notifies the applicant', function () {
        $tenant = Tenant::factory()->create();
        bindLoanChainTenant($tenant);

        \Illuminate\Support\Facades\Notification::fake();

        $cfo = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_cfo' => true,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_admin_manager' => true,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_loan_releasing_officer' => true,
        ]);

        $applicant = Employee::factory()->create();
        $applicantUser = \App\Models\User::factory()->create();
        $applicant->update(['user_id' => $applicantUser->id]);

        $application = LoanApplication::factory()->draft()->create([
            'employee_id' => $applicant->id,
            'urgency_level' => 3,
        ]);

        $service = new LoanApplicationService(new LoanApprovalChainResolver);
        $service->submit($application);

        $rejected = $service->reject($application->refresh(), $cfo, 'Insufficient docs');

        expect($rejected->status)->toBe(LoanApplicationStatus::Rejected);

        $approval = LoanApplicationApproval::where('loan_application_id', $application->id)
            ->where('approval_level', 1)
            ->first();
        expect($approval->decision)->toBe(LeaveApprovalDecision::Rejected);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $applicantUser,
            \App\Notifications\LoanApplicationRejected::class
        );
    });
});
