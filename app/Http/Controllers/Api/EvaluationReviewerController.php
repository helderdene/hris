<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentMethod;
use App\Enums\EvaluationReviewerStatus;
use App\Enums\ReviewerType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationReviewerResource;
use App\Models\Employee;
use App\Models\EvaluationReviewer;
use App\Models\PerformanceCycleParticipant;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EvaluationReviewerController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    /**
     * Display a listing of reviewers for a participant.
     */
    public function index(
        Request $request,
        PerformanceCycleParticipant $participant
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $query = $participant->evaluationReviewers()
            ->with(['reviewerEmployee.position', 'reviewerEmployee.department', 'evaluationResponse'])
            ->orderByRaw("CASE reviewer_type WHEN 'self' THEN 1 WHEN 'manager' THEN 2 WHEN 'peer' THEN 3 WHEN 'direct_report' THEN 4 ELSE 5 END")
            ->orderBy('created_at');

        // Filter by type
        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'pending') {
                $query->pending();
            } elseif ($status === 'in_progress') {
                $query->inProgress();
            } elseif ($status === 'submitted') {
                $query->submitted();
            } elseif ($status === 'declined') {
                $query->declined();
            }
        }

        return EvaluationReviewerResource::collection($query->get());
    }

    /**
     * Store a newly created reviewer.
     */
    public function store(
        Request $request,
        PerformanceCycleParticipant $participant
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        $validated = $request->validate([
            'reviewer_employee_id' => [
                'required',
                'integer',
                Rule::exists(Employee::class, 'id'),
            ],
            'reviewer_type' => [
                'required',
                'string',
                Rule::in(ReviewerType::values()),
            ],
        ]);

        $reviewerType = ReviewerType::from($validated['reviewer_type']);

        // Check for duplicate
        $existing = $participant->evaluationReviewers()
            ->where('reviewer_employee_id', $validated['reviewer_employee_id'])
            ->where('reviewer_type', $reviewerType)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This employee is already assigned as a reviewer with this type.',
                'reviewer' => new EvaluationReviewerResource($existing->load(['reviewerEmployee.position', 'reviewerEmployee.department'])),
            ], 422);
        }

        // Prevent self-assignment to wrong type
        if ($validated['reviewer_employee_id'] === $participant->employee_id && $reviewerType !== ReviewerType::Self) {
            return response()->json([
                'message' => 'Cannot assign the participant as their own peer or direct report reviewer.',
            ], 422);
        }

        $reviewer = $this->evaluationService->assignReviewer(
            participant: $participant,
            reviewerEmployeeId: $validated['reviewer_employee_id'],
            type: $reviewerType,
            method: AssignmentMethod::HrAssigned,
            assignedBy: auth()->id()
        );

        return response()->json([
            'message' => 'Reviewer assigned successfully.',
            'reviewer' => new EvaluationReviewerResource($reviewer->load(['reviewerEmployee.position', 'reviewerEmployee.department'])),
        ], 201);
    }

    /**
     * Display the specified reviewer.
     */
    public function show(
        PerformanceCycleParticipant $participant,
        EvaluationReviewer $reviewer
    ): EvaluationReviewerResource {
        Gate::authorize('can-manage-organization');

        // Validate reviewer belongs to participant
        if ($reviewer->performance_cycle_participant_id !== $participant->id) {
            abort(404);
        }

        return new EvaluationReviewerResource(
            $reviewer->load([
                'reviewerEmployee.position',
                'reviewerEmployee.department',
                'evaluationResponse.competencyRatings.positionCompetency.competency',
                'evaluationResponse.kpiRatings.kpiAssignment.kpiTemplate',
            ])
        );
    }

    /**
     * Remove the specified reviewer.
     */
    public function destroy(
        PerformanceCycleParticipant $participant,
        EvaluationReviewer $reviewer
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        // Validate reviewer belongs to participant
        if ($reviewer->performance_cycle_participant_id !== $participant->id) {
            abort(404);
        }

        // Cannot remove if already submitted
        if ($reviewer->status === EvaluationReviewerStatus::Submitted) {
            return response()->json([
                'message' => 'Cannot remove a reviewer who has already submitted their evaluation.',
            ], 422);
        }

        // Cannot remove self reviewer
        if ($reviewer->reviewer_type === ReviewerType::Self) {
            return response()->json([
                'message' => 'Cannot remove the self-evaluation reviewer.',
            ], 422);
        }

        $reviewer->delete();

        // Update participant status
        $this->evaluationService->updateParticipantEvaluationStatus($participant);

        return response()->json(null, 204);
    }
}
