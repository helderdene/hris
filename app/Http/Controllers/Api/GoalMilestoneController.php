<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\UpdateMilestoneRequest;
use App\Http\Resources\GoalMilestoneResource;
use App\Models\Goal;
use App\Models\GoalMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GoalMilestoneController extends Controller
{
    /**
     * Display a listing of milestones for a goal.
     */
    public function index(Goal $goal): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $milestones = $goal->milestones()
            ->with('completedByUser')
            ->orderBy('sort_order')
            ->get();

        return GoalMilestoneResource::collection($milestones);
    }

    /**
     * Store a newly created milestone.
     */
    public function store(StoreMilestoneRequest $request, Goal $goal): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $maxOrder = $goal->milestones()->max('sort_order') ?? -1;

        $milestone = $goal->milestones()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
        ]);

        return (new GoalMilestoneResource($milestone))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified milestone.
     */
    public function show(Goal $goal, GoalMilestone $milestone): GoalMilestoneResource
    {
        Gate::authorize('can-manage-organization');

        $milestone->load('completedByUser');

        return new GoalMilestoneResource($milestone);
    }

    /**
     * Update the specified milestone.
     */
    public function update(UpdateMilestoneRequest $request, Goal $goal, GoalMilestone $milestone): GoalMilestoneResource
    {
        Gate::authorize('can-manage-organization');

        $milestone->update($request->validated());

        return new GoalMilestoneResource($milestone->fresh());
    }

    /**
     * Remove the specified milestone.
     */
    public function destroy(Goal $goal, GoalMilestone $milestone): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $milestone->delete();

        // Recalculate goal progress
        $goal->calculateProgress();

        return response()->json([
            'message' => 'Milestone deleted successfully.',
        ]);
    }

    /**
     * Toggle milestone completion status.
     */
    public function toggleComplete(Request $request, Goal $goal, GoalMilestone $milestone): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $milestone->toggleComplete($request->user());

        $milestone->refresh();
        $milestone->load('completedByUser');
        $goal->refresh();

        return response()->json([
            'message' => $milestone->is_completed ? 'Milestone marked as complete.' : 'Milestone marked as incomplete.',
            'milestone' => new GoalMilestoneResource($milestone),
            'goal_progress' => $goal->progress_percentage,
        ]);
    }
}
