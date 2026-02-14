<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveGoalRequest;
use App\Http\Requests\RejectGoalRequest;
use App\Http\Resources\GoalListResource;
use App\Http\Resources\GoalResource;
use App\Models\Employee;
use App\Models\Goal;
use App\Services\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamGoalController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * Display a listing of team members' goals.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $manager = $this->getCurrentManager($request);

        $subordinateIds = $manager->subordinates()->pluck('id');

        $query = Goal::whereIn('employee_id', $subordinateIds)
            ->with(['employee', 'keyResults', 'milestones'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('goal_type')) {
            $query->where('goal_type', $request->input('goal_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $goals = $query->paginate($request->input('per_page', 15));

        return GoalListResource::collection($goals);
    }

    /**
     * Get goals pending approval from team members.
     */
    public function pendingApprovals(Request $request): AnonymousResourceCollection
    {
        $manager = $this->getCurrentManager($request);

        $pendingGoals = $this->goalService->getPendingApprovals($manager);

        return GoalListResource::collection($pendingGoals);
    }

    /**
     * Get team goal summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $manager = $this->getCurrentManager($request);

        $summary = $this->goalService->getTeamGoalSummary($manager);

        return response()->json($summary);
    }

    /**
     * Approve a team member's goal.
     */
    public function approve(ApproveGoalRequest $request, Goal $goal): GoalResource
    {
        $manager = $this->getCurrentManager($request);

        // Verify the goal belongs to a subordinate
        $subordinateIds = $manager->subordinates()->pluck('id')->toArray();
        if (! in_array($goal->employee_id, $subordinateIds)) {
            abort(403, 'You can only approve goals for your direct reports.');
        }

        $goal = $this->goalService->approveGoal(
            $goal,
            $request->user(),
            $request->input('feedback')
        );

        $goal->load(['employee', 'keyResults', 'milestones', 'approvedByUser']);

        return new GoalResource($goal);
    }

    /**
     * Reject a team member's goal.
     */
    public function reject(RejectGoalRequest $request, Goal $goal): GoalResource
    {
        $manager = $this->getCurrentManager($request);

        // Verify the goal belongs to a subordinate
        $subordinateIds = $manager->subordinates()->pluck('id')->toArray();
        if (! in_array($goal->employee_id, $subordinateIds)) {
            abort(403, 'You can only reject goals for your direct reports.');
        }

        $goal = $this->goalService->rejectGoal(
            $goal,
            $request->user(),
            $request->input('feedback')
        );

        $goal->load(['employee', 'keyResults', 'milestones', 'approvedByUser']);

        return new GoalResource($goal);
    }

    /**
     * Get the current employee (as manager) from the authenticated user.
     */
    protected function getCurrentManager(Request $request): Employee
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if ($employee === null) {
            abort(403, 'You do not have an employee profile.');
        }

        // Check if the employee has subordinates
        if ($employee->subordinates()->count() === 0) {
            abort(403, 'You do not have any direct reports.');
        }

        return $employee;
    }
}
