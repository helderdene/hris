<?php

namespace App\Http\Controllers\My;

use App\Enums\EvaluationStatus;
use App\Enums\ReviewerType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationResponseResource;
use App\Http\Resources\EvaluationSummaryResource;
use App\Models\EvaluationReviewer;
use App\Models\PerformanceCycleParticipant;
use App\Services\EvaluationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyEvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    /**
     * Display the employee's evaluation hub.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            abort(403, 'No employee profile found.');
        }

        // Get my self-evaluations (as participant)
        $myParticipations = PerformanceCycleParticipant::query()
            ->with(['performanceCycleInstance.performanceCycle', 'evaluationSummary'])
            ->where('employee_id', $employee->id)
            ->where('is_excluded', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($participant) {
                $selfReviewer = $participant->evaluationReviewers()
                    ->byType(ReviewerType::Self)
                    ->first();

                return [
                    'id' => $participant->id,
                    'instance' => [
                        'id' => $participant->performanceCycleInstance->id,
                        'name' => $participant->performanceCycleInstance->name,
                        'cycle_name' => $participant->performanceCycleInstance->performanceCycle?->name,
                        'year' => $participant->performanceCycleInstance->year,
                    ],
                    'evaluation_status' => $participant->evaluation_status?->value ?? 'not_started',
                    'evaluation_status_label' => $participant->evaluation_status?->label() ?? 'Not Started',
                    'evaluation_status_color_class' => $participant->evaluation_status?->colorClass() ?? '',
                    'self_evaluation_due_date' => $participant->self_evaluation_due_date?->format('Y-m-d'),
                    'self_reviewer_id' => $selfReviewer?->id,
                    'self_reviewer_status' => $selfReviewer?->status->value,
                    'self_reviewer_status_label' => $selfReviewer?->status->label(),
                    'has_results' => $participant->evaluation_status === EvaluationStatus::Completed,
                    'is_acknowledged' => $participant->evaluationSummary?->isAcknowledged() ?? false,
                ];
            });

        // Get pending reviews I need to complete (as reviewer)
        $pendingReviews = EvaluationReviewer::query()
            ->with([
                'participant.employee.position',
                'participant.employee.department',
                'participant.performanceCycleInstance',
            ])
            ->where('reviewer_employee_id', $employee->id)
            ->whereNot('reviewer_type', ReviewerType::Self)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($reviewer) => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type->value,
                'reviewer_type_label' => $reviewer->reviewer_type->label(),
                'reviewer_type_color_class' => $reviewer->reviewer_type->colorClass(),
                'status' => $reviewer->status->value,
                'status_label' => $reviewer->status->label(),
                'participant' => [
                    'id' => $reviewer->participant->id,
                    'employee' => [
                        'id' => $reviewer->participant->employee->id,
                        'full_name' => $reviewer->participant->employee->full_name,
                        'position' => $reviewer->participant->employee->position?->title,
                        'department' => $reviewer->participant->employee->department?->name,
                    ],
                ],
                'instance' => [
                    'id' => $reviewer->participant->performanceCycleInstance->id,
                    'name' => $reviewer->participant->performanceCycleInstance->name,
                    'year' => $reviewer->participant->performanceCycleInstance->year,
                ],
                'invited_at' => $reviewer->invited_at?->toISOString(),
                'due_date' => $reviewer->participant->peer_review_due_date?->format('Y-m-d'),
            ]);

        // Get completed reviews
        $completedReviews = EvaluationReviewer::query()
            ->with([
                'participant.employee',
                'participant.performanceCycleInstance',
            ])
            ->where('reviewer_employee_id', $employee->id)
            ->whereNot('reviewer_type', ReviewerType::Self)
            ->where('status', 'submitted')
            ->orderBy('submitted_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($reviewer) => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type->value,
                'reviewer_type_label' => $reviewer->reviewer_type->label(),
                'participant_name' => $reviewer->participant->employee->full_name,
                'instance_name' => $reviewer->participant->performanceCycleInstance->name,
                'submitted_at' => $reviewer->submitted_at?->toISOString(),
            ]);

        return Inertia::render('My/Evaluations/Index', [
            'my_participations' => $myParticipations,
            'pending_reviews' => $pendingReviews,
            'completed_reviews' => $completedReviews,
        ]);
    }

    /**
     * Display the self-evaluation form.
     */
    public function selfEvaluation(string $tenant, PerformanceCycleParticipant $participant): Response
    {
        $user = request()->user();
        $employee = $user->employee;

        // Authorization: Must be the participant
        if (! $employee || $employee->id !== $participant->employee_id) {
            abort(403, 'You are not authorized to view this self-evaluation.');
        }

        // Get or create self reviewer
        $selfReviewer = $participant->evaluationReviewers()
            ->byType(ReviewerType::Self)
            ->first();

        if (! $selfReviewer) {
            // Auto-assign if not exists
            $selfReviewer = $this->evaluationService->assignSelfReviewer($participant);
        }

        $participant->load([
            'employee.position',
            'employee.department',
            'performanceCycleInstance.performanceCycle',
            'kpiAssignments.kpiTemplate',
        ]);

        $selfReviewer->load([
            'evaluationResponse.competencyRatings.positionCompetency.competency',
            'evaluationResponse.kpiRatings.kpiAssignment.kpiTemplate',
        ]);

        // Get competencies for the participant's position
        $competencies = $this->evaluationService->getParticipantCompetencies($participant);

        return Inertia::render('My/Evaluations/SelfEvaluation', [
            'participant' => [
                'id' => $participant->id,
                'employee' => [
                    'id' => $participant->employee->id,
                    'full_name' => $participant->employee->full_name,
                    'position' => $participant->employee->position?->title,
                    'department' => $participant->employee->department?->name,
                ],
                'instance' => [
                    'id' => $participant->performanceCycleInstance->id,
                    'name' => $participant->performanceCycleInstance->name,
                    'cycle_name' => $participant->performanceCycleInstance->performanceCycle?->name,
                    'year' => $participant->performanceCycleInstance->year,
                ],
                'evaluation_status' => $participant->evaluation_status?->value ?? 'not_started',
                'self_evaluation_due_date' => $participant->self_evaluation_due_date?->format('Y-m-d'),
            ],
            'reviewer' => [
                'id' => $selfReviewer->id,
                'status' => $selfReviewer->status->value,
                'status_label' => $selfReviewer->status->label(),
                'can_edit' => $selfReviewer->canEdit(),
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
            'kpi_assignments' => $participant->kpiAssignments->map(fn ($ka) => [
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
            ]),
            'response' => $selfReviewer->evaluationResponse
                ? new EvaluationResponseResource($selfReviewer->evaluationResponse)
                : null,
        ]);
    }

    /**
     * Display the peer/direct report review form.
     */
    public function peerReview(string $tenant, EvaluationReviewer $reviewer): Response
    {
        $user = request()->user();
        $employee = $user->employee;

        // Authorization: Must be the reviewer
        if (! $employee || $employee->id !== $reviewer->reviewer_employee_id) {
            abort(403, 'You are not authorized to complete this review.');
        }

        // Cannot be self reviewer here
        if ($reviewer->reviewer_type === ReviewerType::Self) {
            abort(403, 'Use the self-evaluation page for your own evaluation.');
        }

        $reviewer->load([
            'participant.employee.position',
            'participant.employee.department',
            'participant.performanceCycleInstance.performanceCycle',
            'evaluationResponse.competencyRatings.positionCompetency.competency',
        ]);

        // Get competencies for the participant's position (peers don't see KPIs)
        $competencies = $this->evaluationService->getParticipantCompetencies($reviewer->participant);

        return Inertia::render('My/Evaluations/PeerReview', [
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type->value,
                'reviewer_type_label' => $reviewer->reviewer_type->label(),
                'status' => $reviewer->status->value,
                'status_label' => $reviewer->status->label(),
                'can_edit' => $reviewer->canEdit(),
            ],
            'participant' => [
                'id' => $reviewer->participant->id,
                'employee' => [
                    'id' => $reviewer->participant->employee->id,
                    'full_name' => $reviewer->participant->employee->full_name,
                    'position' => $reviewer->participant->employee->position?->title,
                    'department' => $reviewer->participant->employee->department?->name,
                ],
                'instance' => [
                    'id' => $reviewer->participant->performanceCycleInstance->id,
                    'name' => $reviewer->participant->performanceCycleInstance->name,
                    'cycle_name' => $reviewer->participant->performanceCycleInstance->performanceCycle?->name,
                    'year' => $reviewer->participant->performanceCycleInstance->year,
                ],
                'peer_review_due_date' => $reviewer->participant->peer_review_due_date?->format('Y-m-d'),
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
        ]);
    }

    /**
     * Display the evaluation results.
     */
    public function viewResults(string $tenant, PerformanceCycleParticipant $participant): Response
    {
        $user = request()->user();
        $employee = $user->employee;

        // Authorization: Must be the participant
        if (! $employee || $employee->id !== $participant->employee_id) {
            abort(403, 'You are not authorized to view these results.');
        }

        // Check if results are available
        if ($participant->evaluation_status !== EvaluationStatus::Completed) {
            abort(403, 'Evaluation results are not yet available.');
        }

        $participant->load([
            'employee.position',
            'employee.department',
            'performanceCycleInstance.performanceCycle',
            'evaluationSummary.calibratedBy',
            'evaluationReviewers.evaluationResponse.competencyRatings.positionCompetency.competency',
        ]);

        // Get anonymized peer feedback (aggregate only)
        $peerFeedback = $this->getAnonymizedFeedback($participant);

        return Inertia::render('My/Evaluations/Results', [
            'participant' => [
                'id' => $participant->id,
                'employee' => [
                    'id' => $participant->employee->id,
                    'full_name' => $participant->employee->full_name,
                    'position' => $participant->employee->position?->title,
                    'department' => $participant->employee->department?->name,
                ],
                'instance' => [
                    'id' => $participant->performanceCycleInstance->id,
                    'name' => $participant->performanceCycleInstance->name,
                    'cycle_name' => $participant->performanceCycleInstance->performanceCycle?->name,
                    'year' => $participant->performanceCycleInstance->year,
                ],
            ],
            'summary' => $participant->evaluationSummary
                ? new EvaluationSummaryResource($participant->evaluationSummary)
                : null,
            'peer_feedback' => $peerFeedback,
        ]);
    }

    /**
     * Get anonymized feedback from peers and direct reports.
     *
     * @return array<string, mixed>
     */
    protected function getAnonymizedFeedback(PerformanceCycleParticipant $participant): array
    {
        $peerReviewers = $participant->evaluationReviewers()
            ->whereIn('reviewer_type', [ReviewerType::Peer, ReviewerType::DirectReport])
            ->where('status', 'submitted')
            ->with('evaluationResponse')
            ->get();

        $strengths = [];
        $areasForImprovement = [];
        $developmentSuggestions = [];

        foreach ($peerReviewers as $reviewer) {
            $response = $reviewer->evaluationResponse;
            if (! $response) {
                continue;
            }

            if ($response->strengths) {
                $strengths[] = $response->strengths;
            }
            if ($response->areas_for_improvement) {
                $areasForImprovement[] = $response->areas_for_improvement;
            }
            if ($response->development_suggestions) {
                $developmentSuggestions[] = $response->development_suggestions;
            }
        }

        // Shuffle to anonymize order
        shuffle($strengths);
        shuffle($areasForImprovement);
        shuffle($developmentSuggestions);

        return [
            'response_count' => $peerReviewers->count(),
            'strengths' => $strengths,
            'areas_for_improvement' => $areasForImprovement,
            'development_suggestions' => $developmentSuggestions,
        ];
    }
}
