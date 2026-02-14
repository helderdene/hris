<?php

namespace App\Http\Controllers\Api;

use App\Enums\EvaluationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalibrateEvaluationRequest;
use App\Http\Resources\EvaluationSummaryResource;
use App\Models\PerformanceCycleParticipant;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EvaluationSummaryController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    /**
     * Display the evaluation summary for a participant.
     */
    public function show(
        PerformanceCycleParticipant $participant
    ): JsonResponse {
        // Authorization: Either manage organization OR be the participant (if results are visible)
        $user = auth()->user();
        $employee = $user->employee;

        $canManage = Gate::allows('can-manage-organization');
        $isParticipant = $employee && $employee->id === $participant->employee_id;
        $resultsVisible = $participant->evaluation_status === EvaluationStatus::Completed;

        if (! $canManage && (! $isParticipant || ! $resultsVisible)) {
            abort(403, 'You are not authorized to view this evaluation summary.');
        }

        // Generate or fetch summary
        $summary = $this->evaluationService->generateSummary($participant);

        $participant->load([
            'employee.position',
            'employee.department',
            'evaluationReviewers.reviewerEmployee',
        ]);

        $responseData = [
            'participant' => [
                'id' => $participant->id,
                'evaluation_status' => $participant->evaluation_status->value,
                'evaluation_status_label' => $participant->evaluation_status->label(),
                'employee' => [
                    'id' => $participant->employee->id,
                    'full_name' => $participant->employee->full_name,
                    'employee_number' => $participant->employee->employee_number,
                    'position' => $participant->employee->position ? [
                        'id' => $participant->employee->position->id,
                        'title' => $participant->employee->position->title,
                    ] : null,
                    'department' => $participant->employee->department ? [
                        'id' => $participant->employee->department->id,
                        'name' => $participant->employee->department->name,
                    ] : null,
                ],
            ],
            'summary' => new EvaluationSummaryResource($summary->load('calibratedBy')),
            'reviewer_stats' => [
                'total' => $participant->evaluationReviewers->count(),
                'submitted' => $participant->evaluationReviewers->where('status', 'submitted')->count(),
                'pending' => $participant->evaluationReviewers->where('status', 'pending')->count(),
                'in_progress' => $participant->evaluationReviewers->where('status', 'in_progress')->count(),
                'declined' => $participant->evaluationReviewers->where('status', 'declined')->count(),
            ],
        ];

        // Include detailed reviewer breakdown for managers/admins
        if ($canManage) {
            $responseData['reviewers'] = $participant->evaluationReviewers->map(fn ($r) => [
                'id' => $r->id,
                'reviewer_type' => $r->reviewer_type->value,
                'reviewer_type_label' => $r->reviewer_type->label(),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'reviewer_employee' => [
                    'id' => $r->reviewerEmployee->id,
                    'full_name' => $r->reviewerEmployee->full_name,
                ],
                'submitted_at' => $r->submitted_at?->toISOString(),
            ]);
        }

        return response()->json($responseData);
    }

    /**
     * Calibrate the evaluation scores.
     */
    public function calibrate(
        CalibrateEvaluationRequest $request,
        PerformanceCycleParticipant $participant
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        // Check if ready for calibration
        if (! in_array($participant->evaluation_status, [EvaluationStatus::Calibration, EvaluationStatus::Completed])) {
            return response()->json([
                'message' => 'This evaluation is not ready for calibration. Status: '.$participant->evaluation_status->label(),
            ], 422);
        }

        $summary = $this->evaluationService->calibrate(
            participant: $participant,
            calibrationData: $request->validated(),
            calibratorId: auth()->id()
        );

        return response()->json([
            'message' => 'Evaluation calibrated successfully.',
            'summary' => new EvaluationSummaryResource($summary->load('calibratedBy')),
        ]);
    }

    /**
     * Record employee acknowledgement of results.
     */
    public function acknowledge(
        Request $request,
        PerformanceCycleParticipant $participant
    ): JsonResponse {
        // Authorization: Must be the participant
        $user = auth()->user();
        $employee = $user->employee;

        if (! $employee || $employee->id !== $participant->employee_id) {
            abort(403, 'You are not authorized to acknowledge this evaluation.');
        }

        // Check if evaluation is complete
        if ($participant->evaluation_status !== EvaluationStatus::Completed) {
            return response()->json([
                'message' => 'Evaluation results are not yet available for acknowledgement.',
            ], 422);
        }

        $summary = $participant->evaluationSummary;
        if (! $summary) {
            return response()->json([
                'message' => 'Evaluation summary not found.',
            ], 404);
        }

        // Check if already acknowledged
        if ($summary->isAcknowledged()) {
            return response()->json([
                'message' => 'You have already acknowledged this evaluation.',
            ], 422);
        }

        $comments = $request->input('comments');
        $summary->acknowledge($comments);

        return response()->json([
            'message' => 'Evaluation acknowledged successfully.',
            'summary' => new EvaluationSummaryResource($summary->fresh()),
        ]);
    }

    /**
     * Recalculate summary scores.
     */
    public function recalculate(
        PerformanceCycleParticipant $participant
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        $summary = $this->evaluationService->generateSummary($participant);

        return response()->json([
            'message' => 'Summary recalculated successfully.',
            'summary' => new EvaluationSummaryResource($summary->fresh('calibratedBy')),
        ]);
    }
}
