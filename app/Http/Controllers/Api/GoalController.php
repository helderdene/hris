<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveGoalRequest;
use App\Http\Requests\RecordProgressRequest;
use App\Http\Requests\RejectGoalRequest;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Resources\GoalListResource;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Services\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GoalController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * Display a listing of goals.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $goals = $this->goalService->getGoals([
            'employee_id' => $request->input('employee_id'),
            'department_id' => $request->input('department_id'),
            'goal_type' => $request->input('goal_type'),
            'status' => $request->input('status'),
            'approval_status' => $request->input('approval_status'),
            'performance_cycle_instance_id' => $request->input('performance_cycle_instance_id'),
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by'),
            'sort_dir' => $request->input('sort_dir'),
        ], $request->input('per_page', 15));

        return GoalListResource::collection($goals);
    }

    /**
     * Store a newly created goal.
     */
    public function store(StoreGoalRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $goal = $this->goalService->createGoal($request->validated());

        $goal->load(['employee', 'keyResults', 'milestones']);

        return (new GoalResource($goal))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified goal.
     */
    public function show(string $tenant, Goal $goal): GoalResource
    {
        Gate::authorize('can-manage-organization');

        $goal->load([
            'employee.department',
            'employee.position',
            'parentGoal',
            'childGoals',
            'keyResults.progressEntries',
            'milestones.completedByUser',
            'progressEntries.recordedByUser',
            'comments.user',
            'approvedByUser',
        ]);

        return new GoalResource($goal);
    }

    /**
     * Update the specified goal.
     */
    public function update(UpdateGoalRequest $request, string $tenant, Goal $goal): GoalResource
    {
        Gate::authorize('can-manage-organization');

        $goal = $this->goalService->updateGoal($goal, $request->validated());

        $goal->load(['employee', 'keyResults', 'milestones']);

        return new GoalResource($goal);
    }

    /**
     * Remove the specified goal.
     */
    public function destroy(string $tenant, Goal $goal): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $goal->keyResults()->delete();
        $goal->milestones()->delete();
        $goal->progressEntries()->delete();
        $goal->comments()->delete();
        $goal->delete();

        return response()->json([
            'message' => 'Goal deleted successfully.',
        ]);
    }

    /**
     * Record progress for a goal.
     */
    public function updateProgress(RecordProgressRequest $request, string $tenant, Goal $goal): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        if ($goal->isOkr() && isset($data['goal_key_result_id'])) {
            $keyResult = $goal->keyResults()->findOrFail($data['goal_key_result_id']);
            $entry = $this->goalService->recordKeyResultProgress(
                $keyResult,
                $data['progress_value'],
                $data['notes'] ?? null,
                $request->user()
            );
        } else {
            $entry = $this->goalService->recordGoalProgress(
                $goal,
                $data['progress_percentage'],
                $data['notes'] ?? null,
                $request->user()
            );
        }

        $goal->refresh();
        $goal->load(['keyResults', 'milestones']);

        return response()->json([
            'message' => 'Progress recorded successfully.',
            'goal' => new GoalResource($goal),
        ]);
    }

    /**
     * Mark a goal as completed.
     */
    public function complete(string $tenant, Goal $goal): GoalResource
    {
        Gate::authorize('can-manage-organization');

        $goal = $this->goalService->completeGoal($goal);

        $goal->load(['employee', 'keyResults', 'milestones']);

        return new GoalResource($goal);
    }

    /**
     * Submit a goal for approval.
     */
    public function submitForApproval(string $tenant, Goal $goal): GoalResource
    {
        Gate::authorize('can-manage-organization');

        $goal = $this->goalService->submitForApproval($goal);

        return new GoalResource($goal);
    }

    /**
     * Approve a goal.
     */
    public function approve(ApproveGoalRequest $request, string $tenant, Goal $goal): GoalResource
    {
        Gate::authorize('can-manage-organization');

        $goal = $this->goalService->approveGoal(
            $goal,
            $request->user(),
            $request->input('feedback')
        );

        $goal->load(['employee', 'keyResults', 'milestones', 'approvedByUser']);

        return new GoalResource($goal);
    }

    /**
     * Reject a goal.
     */
    public function reject(RejectGoalRequest $request, string $tenant, Goal $goal): GoalResource
    {
        Gate::authorize('can-manage-organization');

        $goal = $this->goalService->rejectGoal(
            $goal,
            $request->user(),
            $request->input('feedback')
        );

        $goal->load(['employee', 'keyResults', 'milestones', 'approvedByUser']);

        return new GoalResource($goal);
    }
}
