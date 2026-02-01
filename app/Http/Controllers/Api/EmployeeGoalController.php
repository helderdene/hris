<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordProgressRequest;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Resources\GoalListResource;
use App\Http\Resources\GoalResource;
use App\Models\Employee;
use App\Models\Goal;
use App\Services\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeGoalController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * Display a listing of the current employee's goals.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $this->getCurrentEmployee($request);

        $query = Goal::forEmployee($employee->id)
            ->with(['keyResults', 'milestones'])
            ->orderBy('created_at', 'desc');

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
     * Store a newly created goal for the current employee.
     */
    public function store(StoreGoalRequest $request): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        $data = $request->validated();
        $data['employee_id'] = $employee->id;

        $goal = $this->goalService->createGoal($data);

        $goal->load(['keyResults', 'milestones']);

        return (new GoalResource($goal))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified goal (owned by current employee).
     */
    public function show(Request $request, string $tenant, Goal $goal): GoalResource
    {
        $employee = $this->getCurrentEmployee($request);

        // Ensure the goal belongs to the current employee
        if ($goal->employee_id !== $employee->id) {
            abort(403, 'You can only view your own goals.');
        }

        $goal->load([
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
     * Update the specified goal (owned by current employee).
     */
    public function update(UpdateGoalRequest $request, string $tenant, Goal $goal): GoalResource
    {
        $employee = $this->getCurrentEmployee($request);

        // Ensure the goal belongs to the current employee
        if ($goal->employee_id !== $employee->id) {
            abort(403, 'You can only update your own goals.');
        }

        $goal = $this->goalService->updateGoal($goal, $request->validated());

        $goal->load(['keyResults', 'milestones']);

        return new GoalResource($goal);
    }

    /**
     * Record progress for the current employee's goal.
     */
    public function updateProgress(RecordProgressRequest $request, string $tenant, Goal $goal): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        // Ensure the goal belongs to the current employee
        if ($goal->employee_id !== $employee->id) {
            abort(403, 'You can only update progress on your own goals.');
        }

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
     * Get goal statistics for the current employee.
     */
    public function statistics(Request $request): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        $statistics = $this->goalService->getGoalStatistics($employee);

        return response()->json($statistics);
    }

    /**
     * Submit a goal for approval.
     */
    public function submitForApproval(Request $request, string $tenant, Goal $goal): GoalResource
    {
        $employee = $this->getCurrentEmployee($request);

        // Ensure the goal belongs to the current employee
        if ($goal->employee_id !== $employee->id) {
            abort(403, 'You can only submit your own goals for approval.');
        }

        $goal = $this->goalService->submitForApproval($goal);

        return new GoalResource($goal);
    }

    /**
     * Delete the specified goal (owned by current employee).
     */
    public function destroy(Request $request, string $tenant, Goal $goal): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        // Ensure the goal belongs to the current employee
        if ($goal->employee_id !== $employee->id) {
            abort(403, 'You can only delete your own goals.');
        }

        // Only allow deletion of draft goals
        if ($goal->status !== \App\Enums\GoalStatus::Draft) {
            abort(422, 'Only draft goals can be deleted. Consider cancelling the goal instead.');
        }

        $goal->delete();

        return response()->json([
            'message' => 'Goal deleted successfully.',
        ]);
    }

    /**
     * Get the current employee from the authenticated user.
     */
    protected function getCurrentEmployee(Request $request): Employee
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if ($employee === null) {
            abort(403, 'You do not have an employee profile.');
        }

        return $employee;
    }
}
