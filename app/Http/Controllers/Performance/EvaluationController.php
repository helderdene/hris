<?php

namespace App\Http\Controllers\Performance;

use App\Enums\EvaluationStatus;
use App\Enums\ReviewerType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationReviewerResource;
use App\Http\Resources\EvaluationSummaryResource;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Services\EvaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    /**
     * Display the evaluations list page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        // Get available instances for filtering
        $instances = PerformanceCycleInstance::query()
            ->with('performanceCycle')
            ->whereIn('status', ['active', 'in_evaluation', 'closed'])
            ->orderBy('year', 'desc')
            ->orderBy('instance_number')
            ->get()
            ->map(fn ($instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'cycle_name' => $instance->performanceCycle?->name,
                'year' => $instance->year,
                'status' => $instance->status->value,
                'status_label' => $instance->status->label(),
            ]);

        // Build participants query
        $query = PerformanceCycleParticipant::query()
            ->with([
                'employee.position',
                'employee.department',
                'performanceCycleInstance',
                'evaluationReviewers',
            ])
            ->where('is_excluded', false);

        // Filter by instance
        if ($request->filled('instance_id')) {
            $query->where('performance_cycle_instance_id', $request->input('instance_id'));
        }

        // Filter by evaluation status
        if ($request->filled('evaluation_status')) {
            $query->where('evaluation_status', $request->input('evaluation_status'));
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->input('department_id')));
        }

        // Search by employee name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_number', 'like', "%{$search}%")
            );
        }

        $participants = $query->orderBy('created_at', 'desc')->paginate(20);

        // Transform participants with evaluation progress
        $participantsData = $participants->through(function ($participant) {
            $reviewers = $participant->evaluationReviewers;
            $totalReviewers = $reviewers->count();
            $submittedReviewers = $reviewers->where('status', 'submitted')->count();

            return [
                'id' => $participant->id,
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
                'instance' => [
                    'id' => $participant->performanceCycleInstance->id,
                    'name' => $participant->performanceCycleInstance->name,
                    'year' => $participant->performanceCycleInstance->year,
                ],
                'evaluation_status' => $participant->evaluation_status?->value ?? 'not_started',
                'evaluation_status_label' => $participant->evaluation_status?->label() ?? 'Not Started',
                'evaluation_status_color_class' => $participant->evaluation_status?->colorClass() ?? '',
                'progress' => [
                    'total_reviewers' => $totalReviewers,
                    'submitted_reviewers' => $submittedReviewers,
                    'percentage' => $totalReviewers > 0 ? round(($submittedReviewers / $totalReviewers) * 100) : 0,
                ],
                'self_evaluation_due_date' => $participant->self_evaluation_due_date?->format('Y-m-d'),
                'peer_review_due_date' => $participant->peer_review_due_date?->format('Y-m-d'),
                'manager_review_due_date' => $participant->manager_review_due_date?->format('Y-m-d'),
            ];
        });

        // Get evaluation statuses for filter
        $evaluationStatuses = collect(EvaluationStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return Inertia::render('Performance/Evaluations/Index', [
            'participants' => $participantsData,
            'instances' => $instances,
            'evaluationStatuses' => $evaluationStatuses,
            'filters' => [
                'instance_id' => $request->input('instance_id') ? (int) $request->input('instance_id') : null,
                'evaluation_status' => $request->input('evaluation_status'),
                'department_id' => $request->input('department_id') ? (int) $request->input('department_id') : null,
                'search' => $request->input('search'),
            ],
        ]);
    }

    /**
     * Display a participant's evaluation detail page.
     */
    public function show(string $tenant, PerformanceCycleParticipant $participant): Response
    {
        Gate::authorize('can-manage-organization');

        $participant->load([
            'employee.position',
            'employee.department',
            'performanceCycleInstance.performanceCycle',
            'evaluationReviewers.reviewerEmployee.position',
            'evaluationReviewers.reviewerEmployee.department',
            'evaluationReviewers.evaluationResponse.competencyRatings.positionCompetency.competency',
            'evaluationReviewers.evaluationResponse.kpiRatings.kpiAssignment.kpiTemplate',
            'evaluationSummary.calibratedBy',
            'kpiAssignments.kpiTemplate',
        ]);

        // Get competencies for the participant's position
        $competencies = $this->evaluationService->getParticipantCompetencies($participant);

        // Organize reviewers by type
        $reviewersByType = $participant->evaluationReviewers->groupBy(fn ($r) => $r->reviewer_type->value);

        // Build reviewer data
        $reviewersData = [];
        foreach (ReviewerType::cases() as $type) {
            $typeReviewers = $reviewersByType->get($type->value, collect());
            $reviewersData[$type->value] = [
                'label' => $type->label(),
                'color_class' => $type->colorClass(),
                'can_view_kpis' => $type->canViewKpis(),
                'reviewers' => EvaluationReviewerResource::collection($typeReviewers),
                'total' => $typeReviewers->count(),
                'submitted' => $typeReviewers->where('status', 'submitted')->count(),
            ];
        }

        // Generate or fetch summary
        $summary = $participant->evaluationSummary
            ? new EvaluationSummaryResource($participant->evaluationSummary)
            : null;

        return Inertia::render('Performance/Evaluations/Show', [
            'participant' => [
                'id' => $participant->id,
                'employee' => [
                    'id' => $participant->employee->id,
                    'full_name' => $participant->employee->full_name,
                    'employee_number' => $participant->employee->employee_number,
                    'profile_photo_url' => $participant->employee->getProfilePhoto()?->getUrl(),
                    'position' => $participant->employee->position ? [
                        'id' => $participant->employee->position->id,
                        'title' => $participant->employee->position->title,
                    ] : null,
                    'department' => $participant->employee->department ? [
                        'id' => $participant->employee->department->id,
                        'name' => $participant->employee->department->name,
                    ] : null,
                ],
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
                'peer_review_due_date' => $participant->peer_review_due_date?->format('Y-m-d'),
                'manager_review_due_date' => $participant->manager_review_due_date?->format('Y-m-d'),
                'min_peer_reviewers' => $participant->min_peer_reviewers,
                'max_peer_reviewers' => $participant->max_peer_reviewers,
            ],
            'reviewers_by_type' => $reviewersData,
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
                    'metric_unit' => $ka->kpiTemplate->metric_unit,
                ] : null,
            ]),
            'summary' => $summary,
            'final_rating_options' => [
                ['value' => 'exceptional', 'label' => 'Exceptional'],
                ['value' => 'exceeds_expectations', 'label' => 'Exceeds Expectations'],
                ['value' => 'meets_expectations', 'label' => 'Meets Expectations'],
                ['value' => 'needs_improvement', 'label' => 'Needs Improvement'],
                ['value' => 'unsatisfactory', 'label' => 'Unsatisfactory'],
            ],
        ]);
    }
}
