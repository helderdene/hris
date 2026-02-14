<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKeyResultRequest;
use App\Http\Requests\UpdateKeyResultRequest;
use App\Http\Resources\GoalKeyResultResource;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Services\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GoalKeyResultController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * Display a listing of key results for a goal.
     */
    public function index(Goal $goal): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $keyResults = $goal->keyResults()
            ->with('progressEntries')
            ->orderBy('sort_order')
            ->get();

        return GoalKeyResultResource::collection($keyResults);
    }

    /**
     * Store a newly created key result.
     */
    public function store(StoreKeyResultRequest $request, Goal $goal): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $maxOrder = $goal->keyResults()->max('sort_order') ?? -1;

        $keyResult = $goal->keyResults()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'metric_type' => $data['metric_type'],
            'metric_unit' => $data['metric_unit'] ?? null,
            'target_value' => $data['target_value'],
            'starting_value' => $data['starting_value'] ?? 0,
            'weight' => $data['weight'] ?? 1.00,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
        ]);

        return (new GoalKeyResultResource($keyResult))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified key result.
     */
    public function show(Goal $goal, GoalKeyResult $keyResult): GoalKeyResultResource
    {
        Gate::authorize('can-manage-organization');

        $keyResult->load('progressEntries.recordedByUser');

        return new GoalKeyResultResource($keyResult);
    }

    /**
     * Update the specified key result.
     */
    public function update(UpdateKeyResultRequest $request, Goal $goal, GoalKeyResult $keyResult): GoalKeyResultResource
    {
        Gate::authorize('can-manage-organization');

        $keyResult->update($request->validated());

        // Recalculate achievement if target changed
        if ($request->has('target_value')) {
            $keyResult->calculateAchievement();
            $goal->calculateProgress();
        }

        return new GoalKeyResultResource($keyResult->fresh());
    }

    /**
     * Remove the specified key result.
     */
    public function destroy(Goal $goal, GoalKeyResult $keyResult): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $keyResult->progressEntries()->delete();
        $keyResult->delete();

        // Recalculate goal progress
        $goal->calculateProgress();

        return response()->json([
            'message' => 'Key result deleted successfully.',
        ]);
    }

    /**
     * Record progress for a key result.
     */
    public function recordProgress(Request $request, Goal $goal, GoalKeyResult $keyResult): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $request->validate([
            'value' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $entry = $this->goalService->recordKeyResultProgress(
            $keyResult,
            $request->input('value'),
            $request->input('notes'),
            $request->user()
        );

        $keyResult->refresh();
        $goal->refresh();

        return response()->json([
            'message' => 'Progress recorded successfully.',
            'key_result' => new GoalKeyResultResource($keyResult),
            'goal_progress' => $goal->progress_percentage,
        ]);
    }
}
