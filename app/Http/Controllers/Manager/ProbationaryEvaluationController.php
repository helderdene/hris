<?php

namespace App\Http\Controllers\Manager;

use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\ProbationaryMilestone;
use App\Enums\RegularizationRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitProbationaryEvaluationRequest;
use App\Http\Requests\UpdateProbationaryEvaluationRequest;
use App\Http\Resources\ProbationaryEvaluationListResource;
use App\Http\Resources\ProbationaryEvaluationResource;
use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use App\Services\ProbationaryEvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProbationaryEvaluationController extends Controller
{
    public function __construct(
        protected ProbationaryEvaluationService $evaluationService
    ) {}

    /**
     * Display the list of pending probationary evaluations for this manager.
     */
    public function index(Request $request): Response
    {
        $manager = $this->getCurrentManager($request);

        $query = ProbationaryEvaluation::query()
            ->forEvaluator($manager)
            ->with(['employee.department', 'employee.position', 'previousEvaluation'])
            ->orderBy('due_date');

        // Apply filters
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        } else {
            // Default: show only evaluations awaiting manager action
            $query->awaitingManager();
        }

        if ($request->filled('milestone')) {
            $query->byMilestone($request->input('milestone'));
        }

        $evaluations = $query->paginate(15)->withQueryString();

        // Get summary stats
        $summary = [
            'pending' => ProbationaryEvaluation::forEvaluator($manager)
                ->byStatus(ProbationaryEvaluationStatus::Pending)->count(),
            'draft' => ProbationaryEvaluation::forEvaluator($manager)
                ->byStatus(ProbationaryEvaluationStatus::Draft)->count(),
            'revision_requested' => ProbationaryEvaluation::forEvaluator($manager)
                ->byStatus(ProbationaryEvaluationStatus::RevisionRequested)->count(),
            'overdue' => ProbationaryEvaluation::forEvaluator($manager)->overdue()->count(),
        ];

        return Inertia::render('Manager/ProbationaryEvaluations/Index', [
            'evaluations' => ProbationaryEvaluationListResource::collection($evaluations),
            'summary' => $summary,
            'statuses' => ProbationaryEvaluationStatus::options(),
            'milestones' => ProbationaryMilestone::options(),
            'filters' => [
                'status' => $request->input('status'),
                'milestone' => $request->input('milestone'),
            ],
        ]);
    }

    /**
     * Display the evaluation form.
     */
    public function show(Request $request, ProbationaryEvaluation $probationaryEvaluation): Response
    {
        $manager = $this->getCurrentManager($request);

        // Ensure manager is the evaluator
        if ($probationaryEvaluation->evaluator_id !== $manager->id) {
            abort(403, 'You are not authorized to view this evaluation.');
        }

        // Load relationships
        $probationaryEvaluation->load([
            'employee.department',
            'employee.position',
            'evaluator',
            'previousEvaluation',
            'approvals',
        ]);

        // Start evaluation if it's pending
        if ($probationaryEvaluation->status === ProbationaryEvaluationStatus::Pending) {
            $probationaryEvaluation->startEvaluation();
            $probationaryEvaluation->refresh();
        }

        // Initialize criteria ratings if empty
        if (empty($probationaryEvaluation->criteria_ratings)) {
            $probationaryEvaluation->criteria_ratings = $this->evaluationService
                ->initializeCriteriaRatings($probationaryEvaluation->milestone);
            $probationaryEvaluation->save();
            $probationaryEvaluation->refresh();
        }

        return Inertia::render('Manager/ProbationaryEvaluations/Show', [
            'evaluation' => new ProbationaryEvaluationResource($probationaryEvaluation),
            'recommendations' => RegularizationRecommendation::options(),
        ]);
    }

    /**
     * Update the evaluation (save draft).
     */
    public function update(
        UpdateProbationaryEvaluationRequest $request,
        ProbationaryEvaluation $probationaryEvaluation
    ): RedirectResponse {
        $manager = $this->getCurrentManager($request);

        // Ensure manager is the evaluator
        if ($probationaryEvaluation->evaluator_id !== $manager->id) {
            abort(403, 'You are not authorized to update this evaluation.');
        }

        $probationaryEvaluation->update($request->validated());

        // Recalculate overall rating if criteria ratings changed
        if ($request->has('criteria_ratings')) {
            $probationaryEvaluation->overall_rating = $probationaryEvaluation->calculateOverallRating();
            $probationaryEvaluation->save();
        }

        return redirect()->back()->with('success', 'Evaluation saved successfully.');
    }

    /**
     * Submit the evaluation for HR review.
     */
    public function submit(
        SubmitProbationaryEvaluationRequest $request,
        ProbationaryEvaluation $probationaryEvaluation
    ): RedirectResponse {
        $manager = $this->getCurrentManager($request);

        // Ensure manager is the evaluator
        if ($probationaryEvaluation->evaluator_id !== $manager->id) {
            abort(403, 'You are not authorized to submit this evaluation.');
        }

        // Update with validated data first
        $probationaryEvaluation->update($request->validated());

        // Submit for HR review
        $this->evaluationService->submit($probationaryEvaluation);

        return redirect()
            ->route('manager.probationary-evaluations.index')
            ->with('success', 'Evaluation submitted for HR review.');
    }

    /**
     * Get the current employee (as manager) from the authenticated user.
     */
    protected function getCurrentManager(Request $request): Employee
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if ($employee === null) {
            abort(403, 'You do not have an employee profile.');
        }

        return $employee;
    }
}
