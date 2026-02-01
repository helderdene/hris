<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\ProbationaryApprovalDecision;
use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\RegularizationRecommendation;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use App\Models\ProbationaryEvaluationApproval;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProbationaryEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForProbApproval(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForProbApproval(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('ProbationaryEvaluationApproval Model', function () {
    it('creates an approval record with correct attributes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $evaluation = ProbationaryEvaluation::factory()->submitted()->create();

        $approval = ProbationaryEvaluationApproval::factory()
            ->for($evaluation, 'probationaryEvaluation')
            ->pending()
            ->create([
                'approval_level' => 1,
                'approver_type' => 'hr',
            ]);

        expect($approval->probationary_evaluation_id)->toBe($evaluation->id);
        expect($approval->approval_level)->toBe(1);
        expect($approval->approver_type)->toBe('hr');
        expect($approval->decision)->toBe(ProbationaryApprovalDecision::Pending);
        expect($approval->isPending())->toBeTrue();
    });

    it('approves an approval record', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approval = ProbationaryEvaluationApproval::factory()
            ->pending()
            ->create();

        $approval->approve($hrEmployee, 'Looks good');
        $approval->refresh();

        expect($approval->decision)->toBe(ProbationaryApprovalDecision::Approved);
        expect($approval->approver_employee_id)->toBe($hrEmployee->id);
        expect($approval->approver_name)->toBe($hrEmployee->full_name);
        expect($approval->remarks)->toBe('Looks good');
        expect($approval->decided_at)->not->toBeNull();
        expect($approval->isApproved())->toBeTrue();
    });

    it('rejects an approval record', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approval = ProbationaryEvaluationApproval::factory()
            ->pending()
            ->create();

        $approval->reject($hrEmployee, 'Does not meet standards');
        $approval->refresh();

        expect($approval->decision)->toBe(ProbationaryApprovalDecision::Rejected);
        expect($approval->remarks)->toBe('Does not meet standards');
        expect($approval->isRejected())->toBeTrue();
    });

    it('requests revision on an approval record', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approval = ProbationaryEvaluationApproval::factory()
            ->pending()
            ->create();

        $approval->requestRevision($hrEmployee, 'Please add more details');
        $approval->refresh();

        expect($approval->decision)->toBe(ProbationaryApprovalDecision::RevisionRequested);
        expect($approval->remarks)->toBe('Please add more details');
        expect($approval->isRevisionRequested())->toBeTrue();
    });
});

describe('HR Approval Workflow', function () {
    it('transitions evaluation to HR review when submitted', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $evaluation = ProbationaryEvaluation::factory()
            ->draft()
            ->thirdMonth()
            ->withRatings()
            ->create();

        $service = new ProbationaryEvaluationService;
        $submitted = $service->submit($evaluation);

        expect($submitted->status)->toBe(ProbationaryEvaluationStatus::Submitted);
        expect($submitted->approvals()->count())->toBe(1);

        $approval = $submitted->approvals()->first();
        expect($approval->approver_type)->toBe('hr');
        expect($approval->decision)->toBe(ProbationaryApprovalDecision::Pending);
    });

    it('approves evaluation through service', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $hrUser = createTenantUserForProbApproval($tenant, TenantUserRole::Admin);
        $hrEmployee = Employee::factory()->create([
            'user_id' => $hrUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->submitted()
            ->thirdMonth()
            ->withRatings()
            ->create();

        // Create the pending approval
        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $approved = $service->approve($evaluation, $hrEmployee, 'All criteria met');

        expect($approved->status)->toBe(ProbationaryEvaluationStatus::Approved);
        expect($approved->approved_at)->not->toBeNull();

        $approval = $approved->approvals()->first();
        expect($approval->decision)->toBe(ProbationaryApprovalDecision::Approved);
        expect($approval->approver_employee_id)->toBe($hrEmployee->id);
    });

    it('rejects evaluation through service', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->submitted()
            ->thirdMonth()
            ->withRatings()
            ->create();

        // Create the pending approval
        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $rejected = $service->reject($evaluation, $hrEmployee, 'Evaluation incomplete');

        expect($rejected->status)->toBe(ProbationaryEvaluationStatus::Rejected);

        $approval = $rejected->approvals()->first();
        expect($approval->decision)->toBe(ProbationaryApprovalDecision::Rejected);
    });

    it('requests revision through service', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->submitted()
            ->thirdMonth()
            ->withRatings()
            ->create();

        // Create the pending approval
        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $revised = $service->requestRevision($evaluation, $hrEmployee, 'Need more details on areas for improvement');

        expect($revised->status)->toBe(ProbationaryEvaluationStatus::RevisionRequested);

        $approval = $revised->approvals()->first();
        expect($approval->decision)->toBe(ProbationaryApprovalDecision::RevisionRequested);
    });

    it('allows resubmission after revision request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $evaluation = ProbationaryEvaluation::factory()
            ->revisionRequested()
            ->thirdMonth()
            ->withRatings()
            ->create();

        $service = new ProbationaryEvaluationService;
        $resubmitted = $service->submit($evaluation);

        expect($resubmitted->status)->toBe(ProbationaryEvaluationStatus::Submitted);
    });
});

describe('Final Evaluation Processing', function () {
    it('regularizes employee when final evaluation is approved with recommend', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->submitted()
            ->fifthMonth()
            ->withRatings()
            ->withRecommendation(RegularizationRecommendation::Recommend)
            ->create();

        // Create the pending approval
        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $service->approve($evaluation, $hrEmployee, 'Approved');

        $employee->refresh();
        expect($employee->employment_type)->toBe(EmploymentType::Regular);
        expect($employee->regularization_date)->not->toBeNull();
    });

    it('regularizes employee when approved with conditions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->submitted()
            ->fifthMonth()
            ->withRatings()
            ->withRecommendation(RegularizationRecommendation::RecommendWithConditions)
            ->create();

        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $service->approve($evaluation, $hrEmployee, 'Approved with conditions');

        $employee->refresh();
        expect($employee->employment_type)->toBe(EmploymentType::Regular);
    });

    it('does not regularize when probation is extended', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->submitted()
            ->fifthMonth()
            ->withRatings()
            ->withRecommendation(RegularizationRecommendation::ExtendProbation)
            ->create();

        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $service->approve($evaluation, $hrEmployee, 'Extend probation');

        $employee->refresh();
        expect($employee->employment_type)->toBe(EmploymentType::Probationary);
    });

    it('does not regularize when not recommended', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->submitted()
            ->fifthMonth()
            ->withRatings()
            ->withRecommendation(RegularizationRecommendation::NotRecommend)
            ->create();

        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $service->approve($evaluation, $hrEmployee, 'Not recommended for regularization');

        $employee->refresh();
        expect($employee->employment_type)->toBe(EmploymentType::Probationary);
    });

    it('does not process 3rd month evaluation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForProbApproval($tenant);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $hrEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->submitted()
            ->thirdMonth()
            ->withRatings()
            ->create();

        $evaluation->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);

        $service = new ProbationaryEvaluationService;
        $service->approve($evaluation, $hrEmployee, 'Approved');

        $employee->refresh();
        expect($employee->employment_type)->toBe(EmploymentType::Probationary);
    });
});

describe('ProbationaryApprovalDecision Enum', function () {
    it('identifies pending decisions', function () {
        expect(ProbationaryApprovalDecision::Pending->isPending())->toBeTrue();
        expect(ProbationaryApprovalDecision::Approved->isPending())->toBeFalse();
    });

    it('identifies final decisions', function () {
        expect(ProbationaryApprovalDecision::Approved->isFinal())->toBeTrue();
        expect(ProbationaryApprovalDecision::Rejected->isFinal())->toBeTrue();
        expect(ProbationaryApprovalDecision::Pending->isFinal())->toBeFalse();
        expect(ProbationaryApprovalDecision::RevisionRequested->isFinal())->toBeFalse();
    });
});
