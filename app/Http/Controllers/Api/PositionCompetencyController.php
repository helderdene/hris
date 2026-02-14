<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetencyCategory;
use App\Enums\JobLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchUpdatePositionCompetencyRequest;
use App\Http\Requests\StorePositionCompetencyRequest;
use App\Http\Requests\UpdatePositionCompetencyRequest;
use App\Http\Resources\PositionCompetencyResource;
use App\Models\PositionCompetency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PositionCompetencyController extends Controller
{
    /**
     * Display a listing of position competencies.
     *
     * Supports filtering by position_id, competency_id, job_level, category.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = PositionCompetency::query()
            ->with(['position', 'competency', 'proficiencyLevel'])
            ->withActiveCompetency();

        // Filter by position
        if ($request->filled('position_id')) {
            $query->forPosition($request->integer('position_id'));
        }

        // Filter by competency
        if ($request->filled('competency_id')) {
            $query->where('competency_id', $request->integer('competency_id'));
        }

        // Filter by job level
        if ($request->filled('job_level')) {
            $jobLevel = JobLevel::tryFrom($request->input('job_level'));
            if ($jobLevel) {
                $query->forJobLevel($jobLevel);
            }
        }

        // Filter by competency category
        if ($request->filled('category')) {
            $category = CompetencyCategory::tryFrom($request->input('category'));
            if ($category) {
                $query->whereHas('competency', function ($q) use ($category) {
                    $q->where('category', $category->value);
                });
            }
        }

        // Filter mandatory only
        if ($request->boolean('mandatory_only')) {
            $query->mandatory();
        }

        $positionCompetencies = $query
            ->orderBy('position_id')
            ->orderBy('job_level')
            ->orderBy('competency_id')
            ->get();

        return PositionCompetencyResource::collection($positionCompetencies);
    }

    /**
     * Store a newly created position competency assignment.
     */
    public function store(StorePositionCompetencyRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $positionCompetency = PositionCompetency::create($request->validated());

        $positionCompetency->load(['position', 'competency', 'proficiencyLevel']);

        return (new PositionCompetencyResource($positionCompetency))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified position competency.
     */
    public function show(PositionCompetency $positionCompetency): PositionCompetencyResource
    {
        Gate::authorize('can-manage-organization');

        $positionCompetency->load(['position', 'competency', 'proficiencyLevel']);

        return new PositionCompetencyResource($positionCompetency);
    }

    /**
     * Update the specified position competency.
     */
    public function update(
        UpdatePositionCompetencyRequest $request,
        PositionCompetency $positionCompetency
    ): PositionCompetencyResource {
        Gate::authorize('can-manage-organization');

        $positionCompetency->update($request->validated());

        $positionCompetency->load(['position', 'competency', 'proficiencyLevel']);

        return new PositionCompetencyResource($positionCompetency);
    }

    /**
     * Remove the specified position competency.
     */
    public function destroy(PositionCompetency $positionCompetency): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check for existing evaluations
        $evaluationsCount = $positionCompetency->evaluations()->count();

        if ($evaluationsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete this position competency because it has associated evaluations.',
            ], 422);
        }

        $positionCompetency->delete();

        return response()->json([
            'message' => 'Position competency deleted successfully.',
        ]);
    }

    /**
     * Batch update position competencies for a specific position and job level.
     *
     * Accepts an array of competency assignments to create/update/delete efficiently.
     */
    public function batchUpdate(BatchUpdatePositionCompetencyRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $positionId = $validated['position_id'];
        $jobLevel = $validated['job_level'];
        $assignments = $validated['assignments'];

        DB::transaction(function () use ($positionId, $jobLevel, $assignments) {
            // Get existing assignments for this position + job level
            $existing = PositionCompetency::where('position_id', $positionId)
                ->where('job_level', $jobLevel)
                ->get()
                ->keyBy('competency_id');

            $submittedCompetencyIds = collect($assignments)->pluck('competency_id')->toArray();

            // Delete assignments not in the submitted list
            $toDelete = $existing->filter(function ($assignment) use ($submittedCompetencyIds) {
                return ! in_array($assignment->competency_id, $submittedCompetencyIds);
            });

            foreach ($toDelete as $assignment) {
                // Only delete if no evaluations exist
                if ($assignment->evaluations()->count() === 0) {
                    $assignment->delete();
                }
            }

            // Create or update assignments
            foreach ($assignments as $assignment) {
                PositionCompetency::updateOrCreate(
                    [
                        'position_id' => $positionId,
                        'competency_id' => $assignment['competency_id'],
                        'job_level' => $jobLevel,
                    ],
                    [
                        'required_proficiency_level' => $assignment['required_proficiency_level'] ?? 3,
                        'is_mandatory' => $assignment['is_mandatory'] ?? true,
                        'weight' => $assignment['weight'] ?? 1.00,
                        'notes' => $assignment['notes'] ?? null,
                    ]
                );
            }
        });

        // Return updated list
        $updatedAssignments = PositionCompetency::query()
            ->with(['position', 'competency', 'proficiencyLevel'])
            ->where('position_id', $positionId)
            ->where('job_level', $jobLevel)
            ->withActiveCompetency()
            ->get();

        return response()->json([
            'message' => 'Position competencies updated successfully.',
            'data' => PositionCompetencyResource::collection($updatedAssignments),
        ]);
    }
}
