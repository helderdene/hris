<?php

namespace App\Http\Controllers\Performance;

use App\Authorization\EmployeeRecordAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompetencyEvaluationResource;
use App\Http\Resources\ProficiencyLevelResource;
use App\Models\CompetencyEvaluation;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\ProficiencyLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyEvaluationPageController extends Controller
{
    /**
     * Display the competency evaluations page.
     */
    public function index(Request $request): Response
    {
        $isHr = EmployeeRecordAccess::isHr($request->user());
        $managed = fn ($participants) => $participants
            ->whereColumn('manager_id', '!=', 'employee_id')
            ->whereHas('manager', fn ($manager) => $manager->where('user_id', $request->user()->id));

        abort_unless($isHr || PerformanceCycleParticipant::query()->tap($managed)->exists(), 403);

        // Get available instances for filtering
        $instances = PerformanceCycleInstance::query()
            ->with('performanceCycle')
            ->whereIn('status', ['active', 'in_evaluation'])
            ->orderBy('year', 'desc')
            ->orderBy('instance_number')
            ->get()
            ->map(fn ($instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'cycle_name' => $instance->performanceCycle?->name,
                'year' => $instance->year,
            ]);

        // Get participants for the selected instance
        $participants = [];
        $evaluations = [];

        if ($request->filled('instance_id')) {
            $participants = PerformanceCycleParticipant::query()
                ->with(['employee', 'employee.position'])
                ->where('performance_cycle_instance_id', $request->input('instance_id'))
                ->where('is_excluded', false)
                ->when(! $isHr, $managed)
                ->get()
                ->map(fn ($participant) => [
                    'id' => $participant->id,
                    'employee_id' => $participant->employee_id,
                    'employee_name' => $participant->employee?->full_name ?? 'Unknown',
                    'employee_code' => $participant->employee?->employee_code,
                    'position_name' => $participant->employee?->position?->title,
                    'status' => $participant->status,
                ]);

            // Get evaluations if a participant is selected
            if ($request->filled('participant_id')) {
                abort_unless(PerformanceCycleParticipant::findOrFail($request->input('participant_id'))->isRatableBy($request->user()), 403);

                $evaluations = CompetencyEvaluation::query()
                    ->with([
                        'positionCompetency.competency',
                        'positionCompetency.proficiencyLevel',
                    ])
                    ->where('performance_cycle_participant_id', $request->input('participant_id'))
                    ->get();
            }
        }

        // Get proficiency levels for reference
        $proficiencyLevels = ProficiencyLevel::ordered();

        return Inertia::render('Performance/Competencies/Index', [
            'instances' => $instances,
            'participants' => $participants,
            'evaluations' => CompetencyEvaluationResource::collection($evaluations),
            'proficiencyLevels' => ProficiencyLevelResource::collection($proficiencyLevels),
            'filters' => [
                'instance_id' => $request->input('instance_id') ? (int) $request->input('instance_id') : null,
                'participant_id' => $request->input('participant_id') ? (int) $request->input('participant_id') : null,
            ],
        ]);
    }

    /**
     * Display a participant's competency evaluation form.
     */
    public function show(Request $request, PerformanceCycleParticipant $participant): Response
    {
        abort_unless($participant->isRatableBy($request->user()), 403);

        $participant->load([
            'employee.position.positionCompetencies.competency',
            'employee.position.positionCompetencies.proficiencyLevel',
            'performanceCycleInstance.performanceCycle',
            'competencyEvaluations.positionCompetency.competency',
            'competencyEvaluations.positionCompetency.proficiencyLevel',
        ]);

        // Get existing evaluations indexed by position_competency_id
        $existingEvaluations = $participant->competencyEvaluations
            ->keyBy('position_competency_id');

        // Build evaluation data with position competencies
        $positionCompetencies = $participant->employee?->position?->positionCompetencies ?? collect();
        $evaluationData = $positionCompetencies->map(function ($positionCompetency) use ($existingEvaluations, $participant) {
            $existingEvaluation = $existingEvaluations->get($positionCompetency->id);

            return [
                'id' => $existingEvaluation?->id,
                'position_competency_id' => $positionCompetency->id,
                'performance_cycle_participant_id' => $participant->id,
                'competency' => [
                    'id' => $positionCompetency->competency->id,
                    'name' => $positionCompetency->competency->name,
                    'code' => $positionCompetency->competency->code,
                    'description' => $positionCompetency->competency->description,
                    'category' => $positionCompetency->competency->category,
                    'category_label' => $positionCompetency->competency->category_label,
                ],
                'required_proficiency_level' => $positionCompetency->required_proficiency_level,
                'required_proficiency_name' => $positionCompetency->proficiencyLevel?->name,
                'job_level' => $positionCompetency->job_level,
                'job_level_label' => $positionCompetency->job_level_label,
                'is_mandatory' => $positionCompetency->is_mandatory,
                'weight' => (float) $positionCompetency->weight,
                'self_rating' => $existingEvaluation?->self_rating,
                'self_comments' => $existingEvaluation?->self_comments,
                'manager_rating' => $existingEvaluation?->manager_rating,
                'manager_comments' => $existingEvaluation?->manager_comments,
                'final_rating' => $existingEvaluation?->final_rating,
                'evidence' => $existingEvaluation?->evidence ?? [],
                'evaluated_at' => $existingEvaluation?->evaluated_at?->toISOString(),
                'is_complete' => $existingEvaluation?->isComplete() ?? false,
            ];
        })->values();

        // Get proficiency levels for reference
        $proficiencyLevels = ProficiencyLevel::ordered();

        return Inertia::render('Performance/Competencies/Show', [
            'participant' => [
                'id' => $participant->id,
                'employee_id' => $participant->employee_id,
                'employee_name' => $participant->employee?->full_name ?? 'Unknown',
                'employee_code' => $participant->employee?->employee_code,
                'position_name' => $participant->employee?->position?->title,
                'job_level' => $participant->employee?->job_level,
                'job_level_label' => $participant->employee?->job_level?->label(),
                'status' => $participant->status,
                'instance_name' => $participant->performanceCycleInstance?->name,
                'cycle_name' => $participant->performanceCycleInstance?->performanceCycle?->name,
                'year' => $participant->performanceCycleInstance?->year,
            ],
            'canRateSelf' => EmployeeRecordAccess::isHr($request->user()),
            'evaluationData' => $evaluationData,
            'proficiencyLevels' => ProficiencyLevelResource::collection($proficiencyLevels),
            'summary' => [
                'total' => $evaluationData->count(),
                'completed' => $evaluationData->filter(fn ($e) => $e['is_complete'])->count(),
                'with_self_rating' => $evaluationData->filter(fn ($e) => $e['self_rating'] !== null)->count(),
                'with_manager_rating' => $evaluationData->filter(fn ($e) => $e['manager_rating'] !== null)->count(),
            ],
        ]);
    }
}
