<?php

namespace App\Services;

use App\Enums\EmploymentType;
use App\Enums\ProbationaryApprovalDecision;
use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\ProbationaryMilestone;
use App\Enums\RegularizationRecommendation;
use App\Models\Employee;
use App\Models\ProbationaryCriteriaTemplate;
use App\Models\ProbationaryEvaluation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing probationary evaluation workflow.
 *
 * Handles creation, submission, approval, and processing of
 * probationary evaluations at milestone dates.
 */
class ProbationaryEvaluationService
{
    /**
     * Calculate the milestone date from the hire date.
     */
    public function calculateMilestoneDate(Carbon $hireDate, ProbationaryMilestone $milestone): Carbon
    {
        return $hireDate->copy()->addMonths($milestone->monthsFromHire());
    }

    /**
     * Calculate the due date for an evaluation (usually milestone date + buffer).
     */
    public function calculateDueDate(Carbon $milestoneDate, int $bufferDays = 14): Carbon
    {
        return $milestoneDate->copy()->addDays($bufferDays);
    }

    /**
     * Get probationary employees due for evaluation at a specific milestone.
     *
     * @return Collection<int, Employee>
     */
    public function getEmployeesDueForEvaluation(
        ProbationaryMilestone $milestone,
        int $daysAhead = 14
    ): Collection {
        $today = now()->startOfDay();
        $targetDate = now()->addDays($daysAhead)->endOfDay();
        $monthsFromHire = $milestone->monthsFromHire();

        // Calculate the hire date range that would result in milestone dates within our target window
        // If milestone is in X months, we need hire_date where hire_date + X months is between today and targetDate
        // So hire_date should be between (today - X months) and (targetDate - X months)
        $hireStartDate = $today->copy()->subMonths($monthsFromHire)->toDateString();
        $hireEndDate = $targetDate->copy()->subMonths($monthsFromHire)->toDateString();

        return Employee::query()
            ->where('employment_type', EmploymentType::Probationary)
            ->whereNotNull('hire_date')
            ->whereBetween('hire_date', [$hireStartDate, $hireEndDate])
            ->whereDoesntHave('probationaryEvaluations', function ($query) use ($milestone) {
                $query->where('milestone', $milestone->value);
            })
            ->with(['supervisor', 'department', 'position'])
            ->get();
    }

    /**
     * Create evaluations for employees approaching a milestone.
     *
     * @return array<int, ProbationaryEvaluation>
     */
    public function createEvaluationsForMilestone(
        ProbationaryMilestone $milestone,
        int $daysAhead = 14
    ): array {
        $employees = $this->getEmployeesDueForEvaluation($milestone, $daysAhead);
        $created = [];

        foreach ($employees as $employee) {
            $evaluation = $this->createEvaluationForEmployee($employee, $milestone);
            if ($evaluation) {
                $created[] = $evaluation;
            }
        }

        return $created;
    }

    /**
     * Create an evaluation for a specific employee at a milestone.
     */
    public function createEvaluationForEmployee(
        Employee $employee,
        ProbationaryMilestone $milestone
    ): ?ProbationaryEvaluation {
        // Check if evaluation already exists
        $existing = ProbationaryEvaluation::query()
            ->where('employee_id', $employee->id)
            ->where('milestone', $milestone->value)
            ->first();

        if ($existing) {
            return null;
        }

        // Get supervisor as evaluator
        $evaluator = $employee->supervisor;
        if (! $evaluator) {
            return null;
        }

        $milestoneDate = $this->calculateMilestoneDate($employee->hire_date, $milestone);
        $dueDate = $this->calculateDueDate($milestoneDate);

        // Find previous evaluation for 5th month
        $previousEvaluation = null;
        if ($milestone === ProbationaryMilestone::FifthMonth) {
            $previousEvaluation = ProbationaryEvaluation::query()
                ->where('employee_id', $employee->id)
                ->where('milestone', ProbationaryMilestone::ThirdMonth->value)
                ->first();
        }

        return ProbationaryEvaluation::create([
            'employee_id' => $employee->id,
            'evaluator_id' => $evaluator->id,
            'evaluator_name' => $evaluator->full_name,
            'evaluator_position' => $evaluator->position?->name,
            'milestone' => $milestone,
            'milestone_date' => $milestoneDate,
            'due_date' => $dueDate,
            'previous_evaluation_id' => $previousEvaluation?->id,
            'status' => ProbationaryEvaluationStatus::Pending,
        ]);
    }

    /**
     * Submit an evaluation for HR review.
     *
     * @throws ValidationException
     */
    public function submit(ProbationaryEvaluation $evaluation): ProbationaryEvaluation
    {
        // Validate current status allows submission
        if (! in_array($evaluation->status, [
            ProbationaryEvaluationStatus::Draft,
            ProbationaryEvaluationStatus::RevisionRequested,
        ])) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or revision-requested evaluations can be submitted.',
            ]);
        }

        // Validate required fields are filled
        $this->validateEvaluationComplete($evaluation);

        return DB::transaction(function () use ($evaluation) {
            $evaluation->status = ProbationaryEvaluationStatus::Submitted;
            $evaluation->submitted_at = now();
            $evaluation->save();

            // Create HR approval record
            $evaluation->approvals()->create([
                'approval_level' => 1,
                'approver_type' => 'hr',
                'decision' => ProbationaryApprovalDecision::Pending,
            ]);

            return $evaluation->fresh(['approvals', 'employee', 'evaluator']);
        });
    }

    /**
     * Validate that an evaluation has all required fields before submission.
     *
     * @throws ValidationException
     */
    protected function validateEvaluationComplete(ProbationaryEvaluation $evaluation): void
    {
        $errors = [];

        if (empty($evaluation->criteria_ratings)) {
            $errors['criteria_ratings'] = 'All criteria must be rated before submission.';
        }

        if ($evaluation->overall_rating === null) {
            $errors['overall_rating'] = 'Overall rating is required.';
        }

        // For final evaluation, recommendation is required
        if ($evaluation->milestone->isFinalEvaluation() && $evaluation->recommendation === null) {
            $errors['recommendation'] = 'Regularization recommendation is required for final evaluation.';
        }

        // Validate conditional fields based on recommendation
        if ($evaluation->recommendation !== null) {
            if ($evaluation->recommendation->requiresConditions() && empty($evaluation->recommendation_conditions)) {
                $errors['recommendation_conditions'] = 'Conditions are required for conditional recommendation.';
            }

            if ($evaluation->recommendation->requiresExtensionMonths() && $evaluation->extension_months === null) {
                $errors['extension_months'] = 'Extension period is required.';
            }

            if ($evaluation->recommendation->requiresReason() && empty($evaluation->recommendation_reason)) {
                $errors['recommendation_reason'] = 'Reason is required for this recommendation.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Approve an evaluation.
     *
     * @throws ValidationException
     */
    public function approve(
        ProbationaryEvaluation $evaluation,
        Employee $approver,
        ?string $remarks = null
    ): ProbationaryEvaluation {
        // Validate evaluation is awaiting HR
        if (! in_array($evaluation->status, [
            ProbationaryEvaluationStatus::Submitted,
            ProbationaryEvaluationStatus::HrReview,
        ])) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted or HR review evaluations can be approved.',
            ]);
        }

        return DB::transaction(function () use ($evaluation, $approver, $remarks) {
            $evaluation->approve($approver, $remarks);

            // Process the approved evaluation
            if ($evaluation->milestone->isFinalEvaluation()) {
                $this->processApprovedEvaluation($evaluation);
            }

            return $evaluation->fresh(['approvals', 'employee', 'evaluator']);
        });
    }

    /**
     * Reject an evaluation.
     *
     * @throws ValidationException
     */
    public function reject(
        ProbationaryEvaluation $evaluation,
        Employee $approver,
        string $reason
    ): ProbationaryEvaluation {
        // Validate evaluation is awaiting HR
        if (! in_array($evaluation->status, [
            ProbationaryEvaluationStatus::Submitted,
            ProbationaryEvaluationStatus::HrReview,
        ])) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted or HR review evaluations can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($evaluation, $approver, $reason) {
            $evaluation->reject($approver, $reason);

            return $evaluation->fresh(['approvals', 'employee', 'evaluator']);
        });
    }

    /**
     * Request revision from the manager.
     *
     * @throws ValidationException
     */
    public function requestRevision(
        ProbationaryEvaluation $evaluation,
        Employee $approver,
        string $reason
    ): ProbationaryEvaluation {
        // Validate evaluation is awaiting HR
        if (! in_array($evaluation->status, [
            ProbationaryEvaluationStatus::Submitted,
            ProbationaryEvaluationStatus::HrReview,
        ])) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted or HR review evaluations can be sent for revision.',
            ]);
        }

        return DB::transaction(function () use ($evaluation, $approver, $reason) {
            $evaluation->requestRevision($approver, $reason);

            return $evaluation->fresh(['approvals', 'employee', 'evaluator']);
        });
    }

    /**
     * Process an approved final evaluation.
     */
    public function processApprovedEvaluation(ProbationaryEvaluation $evaluation): void
    {
        if (! $evaluation->milestone->isFinalEvaluation()) {
            return;
        }

        $employee = $evaluation->employee;
        $recommendation = $evaluation->recommendation;

        if ($recommendation === null) {
            return;
        }

        match ($recommendation) {
            RegularizationRecommendation::Recommend => $this->regularizeEmployee($employee),
            RegularizationRecommendation::RecommendWithConditions => $this->regularizeEmployee($employee),
            RegularizationRecommendation::ExtendProbation => $this->extendProbation(
                $employee,
                $evaluation->extension_months ?? 3
            ),
            RegularizationRecommendation::NotRecommend => null, // HR will handle termination separately
        };
    }

    /**
     * Regularize an employee (convert from probationary to regular).
     */
    public function regularizeEmployee(Employee $employee): Employee
    {
        $employee->employment_type = EmploymentType::Regular;
        $employee->regularization_date = now()->toDateString();
        $employee->save();

        return $employee;
    }

    /**
     * Extend an employee's probation period.
     */
    public function extendProbation(Employee $employee, int $months = 3): Employee
    {
        // The employee remains probationary
        // Create a new evaluation for the extended period if needed
        $newMilestoneDate = now()->addMonths($months);

        // We might create a new evaluation for the extended period
        // This is tracked by creating a new 5th month evaluation with updated dates

        return $employee;
    }

    /**
     * Get pending evaluations for a manager.
     *
     * @return Collection<int, ProbationaryEvaluation>
     */
    public function getPendingEvaluationsForManager(Employee $manager): Collection
    {
        return ProbationaryEvaluation::query()
            ->forEvaluator($manager)
            ->awaitingManager()
            ->with(['employee.department', 'employee.position', 'previousEvaluation'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get evaluations awaiting HR review.
     *
     * @return Collection<int, ProbationaryEvaluation>
     */
    public function getEvaluationsAwaitingHr(): Collection
    {
        return ProbationaryEvaluation::query()
            ->awaitingHr()
            ->with([
                'employee.department',
                'employee.position',
                'evaluator',
                'previousEvaluation',
                'approvals',
            ])
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Get criteria templates for a milestone.
     *
     * @return Collection<int, ProbationaryCriteriaTemplate>
     */
    public function getCriteriaForMilestone(ProbationaryMilestone $milestone): Collection
    {
        return ProbationaryCriteriaTemplate::getForMilestone($milestone);
    }

    /**
     * Initialize criteria ratings for an evaluation from templates.
     *
     * @return array<int, array{criteria_id: int, name: string, weight: int, rating: null, comments: null}>
     */
    public function initializeCriteriaRatings(ProbationaryMilestone $milestone): array
    {
        $criteria = $this->getCriteriaForMilestone($milestone);

        return $criteria->map(fn ($template) => [
            'criteria_id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'weight' => $template->weight,
            'min_rating' => $template->min_rating,
            'max_rating' => $template->max_rating,
            'is_required' => $template->is_required,
            'rating' => null,
            'comments' => null,
        ])->toArray();
    }

    /**
     * Get summary statistics for HR dashboard.
     *
     * @return array{pending_evaluations: int, awaiting_hr: int, overdue: int, this_month: int}
     */
    public function getHrSummary(): array
    {
        return [
            'pending_evaluations' => ProbationaryEvaluation::awaitingManager()->count(),
            'awaiting_hr' => ProbationaryEvaluation::awaitingHr()->count(),
            'overdue' => ProbationaryEvaluation::overdue()->count(),
            'this_month' => ProbationaryEvaluation::query()
                ->whereMonth('milestone_date', now()->month)
                ->whereYear('milestone_date', now()->year)
                ->count(),
        ];
    }
}
