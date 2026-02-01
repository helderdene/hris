<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\ProbationaryMilestone;
use App\Enums\RegularizationRecommendation;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProbationaryEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForEval(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForEval(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('ProbationaryEvaluation Model', function () {
    it('creates an evaluation with correct attributes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluator = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->forEvaluator($evaluator)
            ->thirdMonth()
            ->create();

        expect($evaluation->employee_id)->toBe($employee->id);
        expect($evaluation->evaluator_id)->toBe($evaluator->id);
        expect($evaluation->milestone)->toBe(ProbationaryMilestone::ThirdMonth);
        expect($evaluation->status)->toBe(ProbationaryEvaluationStatus::Pending);
    });

    it('starts evaluation from pending to draft', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $evaluation = ProbationaryEvaluation::factory()->pending()->create();

        expect($evaluation->status)->toBe(ProbationaryEvaluationStatus::Pending);

        $evaluation->startEvaluation();
        $evaluation->refresh();

        expect($evaluation->status)->toBe(ProbationaryEvaluationStatus::Draft);
    });

    it('links fifth month to third month evaluation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $employee = Employee::factory()->create();

        $thirdMonth = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->thirdMonth()
            ->approved()
            ->withRatings()
            ->create();

        $fifthMonth = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->fifthMonth()
            ->withPreviousEvaluation($thirdMonth)
            ->create();

        expect($fifthMonth->previous_evaluation_id)->toBe($thirdMonth->id);
        expect($fifthMonth->previousEvaluation->id)->toBe($thirdMonth->id);
    });

    it('calculates overall rating from criteria ratings', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $evaluation = ProbationaryEvaluation::factory()->create([
            'criteria_ratings' => [
                ['criteria_id' => 1, 'weight' => 2, 'rating' => 5],
                ['criteria_id' => 2, 'weight' => 1, 'rating' => 4],
                ['criteria_id' => 3, 'weight' => 1, 'rating' => 3],
            ],
        ]);

        $overallRating = $evaluation->calculateOverallRating();

        // (5*2 + 4*1 + 3*1) / (2+1+1) = 17/4 = 4.25
        expect($overallRating)->toBe(4.25);
    });
});

describe('ProbationaryEvaluationService', function () {
    it('creates evaluations for employees due for milestone', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        // Create a probationary employee with hire_date 3 months ago
        $supervisor = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subMonths(3)->toDateString(),
            'supervisor_id' => $supervisor->id,
        ]);

        $service = new ProbationaryEvaluationService;
        $evaluations = $service->createEvaluationsForMilestone(
            ProbationaryMilestone::ThirdMonth,
            daysAhead: 30
        );

        expect(count($evaluations))->toBe(1);
        expect($evaluations[0]->employee_id)->toBe($employee->id);
        expect($evaluations[0]->evaluator_id)->toBe($supervisor->id);
        expect($evaluations[0]->milestone)->toBe(ProbationaryMilestone::ThirdMonth);
    });

    it('calculates milestone date correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $service = new ProbationaryEvaluationService;

        $hireDate = now()->startOfDay();

        $thirdMonthDate = $service->calculateMilestoneDate($hireDate, ProbationaryMilestone::ThirdMonth);
        $fifthMonthDate = $service->calculateMilestoneDate($hireDate, ProbationaryMilestone::FifthMonth);

        expect($thirdMonthDate->toDateString())->toBe($hireDate->copy()->addMonths(3)->toDateString());
        expect($fifthMonthDate->toDateString())->toBe($hireDate->copy()->addMonths(5)->toDateString());
    });

    it('validates evaluation is complete before submission', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $evaluation = ProbationaryEvaluation::factory()
            ->draft()
            ->create([
                'criteria_ratings' => null,
                'overall_rating' => null,
            ]);

        $service = new ProbationaryEvaluationService;

        expect(fn () => $service->submit($evaluation))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('submits evaluation for HR review', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $evaluation = ProbationaryEvaluation::factory()
            ->draft()
            ->thirdMonth()
            ->withRatings()
            ->create();

        $service = new ProbationaryEvaluationService;
        $submitted = $service->submit($evaluation);

        expect($submitted->status)->toBe(ProbationaryEvaluationStatus::Submitted);
        expect($submitted->submitted_at)->not->toBeNull();
        expect($submitted->approvals()->count())->toBe(1);
    });

    it('requires recommendation for final evaluation submission', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $evaluation = ProbationaryEvaluation::factory()
            ->draft()
            ->fifthMonth()
            ->withRatings()
            ->create([
                'recommendation' => null,
            ]);

        $service = new ProbationaryEvaluationService;

        expect(fn () => $service->submit($evaluation))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('regularizes employee on approved final evaluation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEval($tenant);

        $employee = Employee::factory()->create([
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $hrUser = createTenantUserForEval($tenant, TenantUserRole::Admin);
        $hrEmployee = Employee::factory()->create([
            'user_id' => $hrUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $evaluation = ProbationaryEvaluation::factory()
            ->forEmployee($employee)
            ->submitted()
            ->fifthMonth()
            ->withRatings()
            ->withRecommendation(RegularizationRecommendation::Recommend)
            ->create();

        $service = new ProbationaryEvaluationService;
        $approved = $service->approve($evaluation, $hrEmployee, 'Approved for regularization');

        expect($approved->status)->toBe(ProbationaryEvaluationStatus::Approved);

        $employee->refresh();
        expect($employee->employment_type)->toBe(EmploymentType::Regular);
        expect($employee->regularization_date)->not->toBeNull();
    });
});

describe('ProbationaryEvaluation Status Transitions', function () {
    it('allows correct status transitions', function () {
        expect(ProbationaryEvaluationStatus::Pending->canTransitionTo(ProbationaryEvaluationStatus::Draft))->toBeTrue();
        expect(ProbationaryEvaluationStatus::Draft->canTransitionTo(ProbationaryEvaluationStatus::Submitted))->toBeTrue();
        expect(ProbationaryEvaluationStatus::Submitted->canTransitionTo(ProbationaryEvaluationStatus::HrReview))->toBeTrue();
        expect(ProbationaryEvaluationStatus::HrReview->canTransitionTo(ProbationaryEvaluationStatus::Approved))->toBeTrue();
        expect(ProbationaryEvaluationStatus::HrReview->canTransitionTo(ProbationaryEvaluationStatus::Rejected))->toBeTrue();
        expect(ProbationaryEvaluationStatus::HrReview->canTransitionTo(ProbationaryEvaluationStatus::RevisionRequested))->toBeTrue();
    });

    it('prevents invalid status transitions', function () {
        expect(ProbationaryEvaluationStatus::Pending->canTransitionTo(ProbationaryEvaluationStatus::Approved))->toBeFalse();
        expect(ProbationaryEvaluationStatus::Draft->canTransitionTo(ProbationaryEvaluationStatus::Approved))->toBeFalse();
        expect(ProbationaryEvaluationStatus::Approved->canTransitionTo(ProbationaryEvaluationStatus::Pending))->toBeFalse();
    });

    it('identifies final statuses', function () {
        expect(ProbationaryEvaluationStatus::Approved->isFinal())->toBeTrue();
        expect(ProbationaryEvaluationStatus::Rejected->isFinal())->toBeTrue();
        expect(ProbationaryEvaluationStatus::Pending->isFinal())->toBeFalse();
        expect(ProbationaryEvaluationStatus::Draft->isFinal())->toBeFalse();
    });
});

describe('ProbationaryMilestone Enum', function () {
    it('calculates correct months from hire', function () {
        expect(ProbationaryMilestone::ThirdMonth->monthsFromHire())->toBe(3);
        expect(ProbationaryMilestone::FifthMonth->monthsFromHire())->toBe(5);
    });

    it('identifies final evaluation', function () {
        expect(ProbationaryMilestone::ThirdMonth->isFinalEvaluation())->toBeFalse();
        expect(ProbationaryMilestone::FifthMonth->isFinalEvaluation())->toBeTrue();
    });

    it('provides next and previous milestones', function () {
        expect(ProbationaryMilestone::ThirdMonth->nextMilestone())->toBe(ProbationaryMilestone::FifthMonth);
        expect(ProbationaryMilestone::FifthMonth->nextMilestone())->toBeNull();

        expect(ProbationaryMilestone::ThirdMonth->previousMilestone())->toBeNull();
        expect(ProbationaryMilestone::FifthMonth->previousMilestone())->toBe(ProbationaryMilestone::ThirdMonth);
    });
});

describe('RegularizationRecommendation Enum', function () {
    it('identifies conditional requirements', function () {
        expect(RegularizationRecommendation::Recommend->requiresConditions())->toBeFalse();
        expect(RegularizationRecommendation::RecommendWithConditions->requiresConditions())->toBeTrue();

        expect(RegularizationRecommendation::ExtendProbation->requiresExtensionMonths())->toBeTrue();
        expect(RegularizationRecommendation::Recommend->requiresExtensionMonths())->toBeFalse();

        expect(RegularizationRecommendation::NotRecommend->requiresReason())->toBeTrue();
        expect(RegularizationRecommendation::Recommend->requiresReason())->toBeFalse();
    });

    it('identifies positive recommendations', function () {
        expect(RegularizationRecommendation::Recommend->isPositive())->toBeTrue();
        expect(RegularizationRecommendation::RecommendWithConditions->isPositive())->toBeTrue();
        expect(RegularizationRecommendation::ExtendProbation->isPositive())->toBeFalse();
        expect(RegularizationRecommendation::NotRecommend->isPositive())->toBeFalse();
    });
});
