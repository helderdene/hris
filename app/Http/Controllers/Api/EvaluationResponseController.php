<?php

namespace App\Http\Controllers\Api;

use App\Enums\EvaluationReviewerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvaluationResponseRequest;
use App\Http\Resources\EvaluationResponseResource;
use App\Models\EvaluationReviewer;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EvaluationResponseController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    /**
     * Display the evaluation response for a reviewer.
     */
    public function show(
        EvaluationReviewer $reviewer
    ): JsonResponse {
        // Authorization: Either manage organization OR be the reviewer
        $user = auth()->user();
        $employee = $user->employee;

        $canManage = Gate::allows('can-manage-organization');
        $isReviewer = $employee && $employee->id === $reviewer->reviewer_employee_id;

        if (! $canManage && ! $isReviewer) {
            abort(403, 'You are not authorized to view this evaluation.');
        }

        $reviewer->load([
            'participant.employee.position',
            'participant.employee.department',
            'participant.kpiAssignments.kpiTemplate',
            'evaluationResponse.competencyRatings.positionCompetency.competency',
            'evaluationResponse.kpiRatings.kpiAssignment.kpiTemplate',
        ]);

        // Get competencies for the participant's position
        $competencies = $this->evaluationService->getParticipantCompetencies($reviewer->participant);

        $responseData = [
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type->value,
                'reviewer_type_label' => $reviewer->reviewer_type->label(),
                'status' => $reviewer->status->value,
                'status_label' => $reviewer->status->label(),
                'can_view_kpis' => $reviewer->canViewKpis(),
                'can_edit' => $reviewer->canEdit(),
            ],
            'participant' => [
                'id' => $reviewer->participant->id,
                'employee' => [
                    'id' => $reviewer->participant->employee->id,
                    'full_name' => $reviewer->participant->employee->full_name,
                    'employee_number' => $reviewer->participant->employee->employee_number,
                    'position' => $reviewer->participant->employee->position ? [
                        'id' => $reviewer->participant->employee->position->id,
                        'title' => $reviewer->participant->employee->position->title,
                    ] : null,
                    'department' => $reviewer->participant->employee->department ? [
                        'id' => $reviewer->participant->employee->department->id,
                        'name' => $reviewer->participant->employee->department->name,
                    ] : null,
                ],
            ],
            'competencies' => $competencies->map(fn ($pc) => [
                'id' => $pc->id,
                'required_proficiency_level' => $pc->required_proficiency_level,
                'is_mandatory' => $pc->is_mandatory,
                'weight' => $pc->weight,
                'competency' => [
                    'id' => $pc->competency->id,
                    'name' => $pc->competency->name,
                    'code' => $pc->competency->code,
                    'description' => $pc->competency->description,
                    'category' => $pc->competency->category?->value,
                    'category_label' => $pc->competency->category?->label(),
                ],
            ]),
            'response' => $reviewer->evaluationResponse
                ? new EvaluationResponseResource($reviewer->evaluationResponse)
                : null,
        ];

        // Include KPIs only if reviewer can view them
        if ($reviewer->canViewKpis()) {
            $responseData['kpi_assignments'] = $reviewer->participant->kpiAssignments->map(fn ($ka) => [
                'id' => $ka->id,
                'target_value' => $ka->target_value,
                'actual_value' => $ka->actual_value,
                'weight' => $ka->weight,
                'achievement_percentage' => $ka->achievement_percentage,
                'status' => $ka->status?->value,
                'status_label' => $ka->status?->label(),
                'kpi_template' => $ka->kpiTemplate ? [
                    'id' => $ka->kpiTemplate->id,
                    'name' => $ka->kpiTemplate->name,
                    'code' => $ka->kpiTemplate->code,
                    'description' => $ka->kpiTemplate->description,
                    'metric_unit' => $ka->kpiTemplate->metric_unit,
                ] : null,
            ]);
        }

        return response()->json($responseData);
    }

    /**
     * Store or update the evaluation response.
     */
    public function store(
        StoreEvaluationResponseRequest $request,
        EvaluationReviewer $reviewer
    ): JsonResponse {
        // Authorization: Must be the reviewer
        $user = auth()->user();
        $employee = $user->employee;

        if (! $employee || $employee->id !== $reviewer->reviewer_employee_id) {
            abort(403, 'You are not authorized to submit this evaluation.');
        }

        // Check if reviewer can still edit
        if (! $reviewer->canEdit()) {
            return response()->json([
                'message' => 'This evaluation has already been submitted and cannot be modified.',
            ], 422);
        }

        $data = $request->validated();

        // Remove KPI ratings if reviewer cannot view KPIs
        if (! $reviewer->canViewKpis() && isset($data['kpi_ratings'])) {
            unset($data['kpi_ratings']);
        }

        // Check if this is a submission or just a draft save
        $isSubmission = $request->boolean('submit', false);

        if ($isSubmission) {
            // Save draft first, then submit
            $response = $this->evaluationService->saveEvaluationDraft($reviewer, $data);
            $response = $this->evaluationService->submitEvaluation($reviewer);

            return response()->json([
                'message' => 'Evaluation submitted successfully.',
                'response' => new EvaluationResponseResource($response->load([
                    'competencyRatings.positionCompetency.competency',
                    'kpiRatings.kpiAssignment.kpiTemplate',
                ])),
            ]);
        }

        // Save as draft
        $response = $this->evaluationService->saveEvaluationDraft($reviewer, $data);

        return response()->json([
            'message' => 'Draft saved successfully.',
            'response' => new EvaluationResponseResource($response->load([
                'competencyRatings.positionCompetency.competency',
                'kpiRatings.kpiAssignment.kpiTemplate',
            ])),
        ]);
    }

    /**
     * Submit the evaluation (final).
     */
    public function submit(
        EvaluationReviewer $reviewer
    ): JsonResponse {
        // Authorization: Must be the reviewer
        $user = auth()->user();
        $employee = $user->employee;

        if (! $employee || $employee->id !== $reviewer->reviewer_employee_id) {
            abort(403, 'You are not authorized to submit this evaluation.');
        }

        // Check if reviewer can still edit
        if (! $reviewer->canEdit()) {
            return response()->json([
                'message' => 'This evaluation has already been submitted.',
            ], 422);
        }

        // Check if response exists
        if (! $reviewer->evaluationResponse) {
            return response()->json([
                'message' => 'No evaluation response found to submit. Please save your evaluation first.',
            ], 422);
        }

        $response = $this->evaluationService->submitEvaluation($reviewer);

        return response()->json([
            'message' => 'Evaluation submitted successfully.',
            'response' => new EvaluationResponseResource($response),
        ]);
    }

    /**
     * Decline to review.
     */
    public function decline(
        Request $request,
        EvaluationReviewer $reviewer
    ): JsonResponse {
        // Authorization: Must be the reviewer
        $user = auth()->user();
        $employee = $user->employee;

        if (! $employee || $employee->id !== $reviewer->reviewer_employee_id) {
            abort(403, 'You are not authorized to decline this evaluation.');
        }

        // Check if reviewer can still decline
        if (! in_array($reviewer->status, [EvaluationReviewerStatus::Pending, EvaluationReviewerStatus::InProgress])) {
            return response()->json([
                'message' => 'This evaluation cannot be declined in its current state.',
            ], 422);
        }

        $reason = $request->input('reason');
        $reviewer = $this->evaluationService->declineReview($reviewer, $reason);

        return response()->json([
            'message' => 'Evaluation declined.',
            'reviewer' => [
                'id' => $reviewer->id,
                'status' => $reviewer->status->value,
                'declined_at' => $reviewer->declined_at?->toISOString(),
                'decline_reason' => $reviewer->decline_reason,
            ],
        ]);
    }
}
