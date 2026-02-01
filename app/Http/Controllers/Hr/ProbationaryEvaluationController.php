<?php

namespace App\Http\Controllers\Hr;

use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\ProbationaryMilestone;
use App\Enums\RegularizationRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveProbationaryEvaluationRequest;
use App\Http\Requests\RejectProbationaryEvaluationRequest;
use App\Http\Requests\RequestProbationaryRevisionRequest;
use App\Http\Resources\ProbationaryEvaluationListResource;
use App\Http\Resources\ProbationaryEvaluationResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use App\Services\ProbationaryEvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProbationaryEvaluationController extends Controller
{
    public function __construct(
        protected ProbationaryEvaluationService $evaluationService
    ) {}

    /**
     * Display the list of probationary evaluations for HR review.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = ProbationaryEvaluation::query()
            ->with([
                'employee.department',
                'employee.position',
                'evaluator',
                'approvals',
            ])
            ->orderBy('submitted_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('milestone')) {
            $query->byMilestone($request->input('milestone'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        if ($request->filled('awaiting_action')) {
            $query->awaitingHr();
        }

        $evaluations = $query->paginate(20)->withQueryString();

        // Get departments for filter
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get summary
        $summary = $this->evaluationService->getHrSummary();

        return Inertia::render('Hr/ProbationaryEvaluations/Index', [
            'evaluations' => ProbationaryEvaluationListResource::collection($evaluations),
            'departments' => $departments,
            'summary' => $summary,
            'statuses' => ProbationaryEvaluationStatus::options(),
            'milestones' => ProbationaryMilestone::options(),
            'filters' => [
                'status' => $request->input('status'),
                'milestone' => $request->input('milestone'),
                'department_id' => $request->input('department_id') ? (int) $request->input('department_id') : null,
                'awaiting_action' => $request->boolean('awaiting_action'),
            ],
        ]);
    }

    /**
     * Display the evaluation for HR review.
     */
    public function show(Request $request, ProbationaryEvaluation $probationaryEvaluation): Response
    {
        Gate::authorize('can-manage-organization');

        // Mark as under HR review if it's just submitted
        if ($probationaryEvaluation->status === ProbationaryEvaluationStatus::Submitted) {
            $probationaryEvaluation->markAsHrReview();
            $probationaryEvaluation->refresh();
        }

        // Load relationships
        $probationaryEvaluation->load([
            'employee.department',
            'employee.position',
            'evaluator',
            'previousEvaluation',
            'approvals',
        ]);

        return Inertia::render('Hr/ProbationaryEvaluations/Show', [
            'evaluation' => new ProbationaryEvaluationResource($probationaryEvaluation),
            'recommendations' => RegularizationRecommendation::options(),
        ]);
    }

    /**
     * Approve the evaluation.
     */
    public function approve(
        ApproveProbationaryEvaluationRequest $request,
        ProbationaryEvaluation $probationaryEvaluation
    ): RedirectResponse {
        Gate::authorize('can-manage-organization');

        $hrEmployee = $this->getCurrentHrEmployee($request);

        $this->evaluationService->approve(
            $probationaryEvaluation,
            $hrEmployee,
            $request->input('remarks')
        );

        return redirect()
            ->route('hr.probationary-evaluations.index')
            ->with('success', 'Evaluation approved successfully.');
    }

    /**
     * Reject the evaluation.
     */
    public function reject(
        RejectProbationaryEvaluationRequest $request,
        ProbationaryEvaluation $probationaryEvaluation
    ): RedirectResponse {
        Gate::authorize('can-manage-organization');

        $hrEmployee = $this->getCurrentHrEmployee($request);

        $this->evaluationService->reject(
            $probationaryEvaluation,
            $hrEmployee,
            $request->input('reason')
        );

        return redirect()
            ->route('hr.probationary-evaluations.index')
            ->with('success', 'Evaluation rejected.');
    }

    /**
     * Request revision from the manager.
     */
    public function requestRevision(
        RequestProbationaryRevisionRequest $request,
        ProbationaryEvaluation $probationaryEvaluation
    ): RedirectResponse {
        Gate::authorize('can-manage-organization');

        $hrEmployee = $this->getCurrentHrEmployee($request);

        $this->evaluationService->requestRevision(
            $probationaryEvaluation,
            $hrEmployee,
            $request->input('reason')
        );

        return redirect()
            ->route('hr.probationary-evaluations.index')
            ->with('success', 'Revision requested from manager.');
    }

    /**
     * Get the current HR employee from the authenticated user.
     */
    protected function getCurrentHrEmployee(Request $request): Employee
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if ($employee === null) {
            abort(403, 'You do not have an employee profile.');
        }

        return $employee;
    }
}
