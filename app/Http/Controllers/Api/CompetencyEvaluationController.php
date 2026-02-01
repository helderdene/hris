<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetencyEvaluationRequest;
use App\Http\Requests\UpdateCompetencyEvaluationRequest;
use App\Http\Resources\CompetencyEvaluationResource;
use App\Models\CompetencyEvaluation;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CompetencyEvaluationController extends Controller
{
    /**
     * Display a listing of competency evaluations.
     *
     * Supports filtering by participant_id.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = CompetencyEvaluation::query()
            ->with([
                'positionCompetency.competency',
                'positionCompetency.proficiencyLevel',
                'performanceCycleParticipant.employee',
            ]);

        // Filter by participant
        if ($request->filled('participant_id')) {
            $query->forParticipant($request->integer('participant_id'));
        }

        // Filter by completion status
        if ($request->has('completed')) {
            if ($request->boolean('completed')) {
                $query->withFinalRating();
            } else {
                $query->whereNull('final_rating');
            }
        }

        $evaluations = $query
            ->orderBy('position_competency_id')
            ->get();

        return CompetencyEvaluationResource::collection($evaluations);
    }

    /**
     * Store a newly created competency evaluation.
     */
    public function store(StoreCompetencyEvaluationRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $evaluation = CompetencyEvaluation::create($request->validated());

        $evaluation->load([
            'positionCompetency.competency',
            'positionCompetency.proficiencyLevel',
        ]);

        return (new CompetencyEvaluationResource($evaluation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified competency evaluation.
     */
    public function show(string $tenant, CompetencyEvaluation $competencyEvaluation): CompetencyEvaluationResource
    {
        Gate::authorize('can-manage-organization');

        $competencyEvaluation->load([
            'positionCompetency.competency',
            'positionCompetency.proficiencyLevel',
            'performanceCycleParticipant.employee',
        ]);

        return new CompetencyEvaluationResource($competencyEvaluation);
    }

    /**
     * Update the specified competency evaluation.
     */
    public function update(
        UpdateCompetencyEvaluationRequest $request,
        string $tenant,
        CompetencyEvaluation $competencyEvaluation
    ): CompetencyEvaluationResource {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        // Auto-set evaluated_at when final_rating is set
        if (isset($data['final_rating']) && $data['final_rating'] !== null) {
            $data['evaluated_at'] = now();
        }

        $competencyEvaluation->update($data);

        $competencyEvaluation->load([
            'positionCompetency.competency',
            'positionCompetency.proficiencyLevel',
        ]);

        return new CompetencyEvaluationResource($competencyEvaluation);
    }

    /**
     * Remove the specified competency evaluation.
     */
    public function destroy(string $tenant, CompetencyEvaluation $competencyEvaluation): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $competencyEvaluation->delete();

        return response()->json([
            'message' => 'Competency evaluation deleted successfully.',
        ]);
    }

    /**
     * Get competency evaluations for a specific participant.
     */
    public function participantEvaluations(
        string $tenant,
        PerformanceCycleParticipant $participant
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $evaluations = $participant->competencyEvaluations()
            ->with([
                'positionCompetency.competency',
                'positionCompetency.proficiencyLevel',
            ])
            ->get();

        return CompetencyEvaluationResource::collection($evaluations);
    }

    /**
     * Submit self-rating for an evaluation.
     */
    public function submitSelfRating(
        Request $request,
        string $tenant,
        CompetencyEvaluation $competencyEvaluation
    ): CompetencyEvaluationResource {
        // Allow the employee to submit their own self-rating
        $request->validate([
            'self_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'self_comments' => ['nullable', 'string'],
        ]);

        $competencyEvaluation->update([
            'self_rating' => $request->input('self_rating'),
            'self_comments' => $request->input('self_comments'),
        ]);

        $competencyEvaluation->load([
            'positionCompetency.competency',
            'positionCompetency.proficiencyLevel',
        ]);

        return new CompetencyEvaluationResource($competencyEvaluation);
    }

    /**
     * Submit manager rating for an evaluation.
     */
    public function submitManagerRating(
        Request $request,
        string $tenant,
        CompetencyEvaluation $competencyEvaluation
    ): CompetencyEvaluationResource {
        Gate::authorize('can-manage-organization');

        $request->validate([
            'manager_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'manager_comments' => ['nullable', 'string'],
            'final_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $data = [
            'manager_rating' => $request->input('manager_rating'),
            'manager_comments' => $request->input('manager_comments'),
        ];

        // If final rating is provided, set it and mark as evaluated
        if ($request->filled('final_rating')) {
            $data['final_rating'] = $request->input('final_rating');
            $data['evaluated_at'] = now();
        }

        $competencyEvaluation->update($data);

        $competencyEvaluation->load([
            'positionCompetency.competency',
            'positionCompetency.proficiencyLevel',
        ]);

        return new CompetencyEvaluationResource($competencyEvaluation);
    }
}
