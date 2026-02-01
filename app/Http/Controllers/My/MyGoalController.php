<?php

namespace App\Http\Controllers\My;

use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\GoalVisibility;
use App\Enums\KeyResultMetricType;
use App\Http\Controllers\Controller;
use App\Http\Resources\GoalListResource;
use App\Http\Resources\GoalResource;
use App\Models\Employee;
use App\Models\Goal;
use App\Services\GoalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyGoalController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * Display the employee's goals.
     */
    public function index(Request $request): Response
    {
        $employee = $this->getCurrentEmployee($request);

        $query = Goal::forEmployee($employee->id)
            ->with(['keyResults', 'milestones'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('goal_type')) {
            $query->where('goal_type', $request->input('goal_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $goals = $query->paginate(15)->withQueryString();

        // Get statistics
        $statistics = $this->goalService->getGoalStatistics($employee);

        return Inertia::render('My/Goals/Index', [
            'goals' => GoalListResource::collection($goals),
            'statistics' => $statistics,
            'goalTypes' => $this->getGoalTypeOptions(),
            'goalStatuses' => $this->getGoalStatusOptions(),
            'filters' => [
                'goal_type' => $request->input('goal_type'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Display the create goal page.
     */
    public function create(Request $request): Response
    {
        $employee = $this->getCurrentEmployee($request);

        // Get available parent goals for alignment
        $availableParentGoals = Goal::where('visibility', '!=', 'private')
            ->where('status', GoalStatus::Active)
            ->whereNull('completed_at')
            ->with('employee')
            ->get()
            ->map(fn ($goal) => [
                'id' => $goal->id,
                'title' => $goal->title,
                'goal_type' => $goal->goal_type?->value,
                'owner_name' => $goal->employee?->full_name,
            ]);

        return Inertia::render('My/Goals/Create', [
            'availableParentGoals' => $availableParentGoals,
            'goalTypes' => $this->getGoalTypeOptions(),
            'priorities' => $this->getPriorityOptions(),
            'visibilityOptions' => $this->getVisibilityOptions(),
            'metricTypes' => $this->getMetricTypeOptions(),
        ]);
    }

    /**
     * Display a specific goal.
     */
    public function show(Request $request, string $tenant, Goal $goal): Response
    {
        $employee = $this->getCurrentEmployee($request);

        // Verify ownership
        if ($goal->employee_id !== $employee->id) {
            abort(403, 'You can only view your own goals.');
        }

        $goal->load([
            'parentGoal',
            'childGoals',
            'keyResults.progressEntries.recordedByUser',
            'milestones.completedByUser',
            'progressEntries.recordedByUser',
            'comments.user',
            'approvedByUser',
        ]);

        return Inertia::render('My/Goals/Show', [
            'goal' => new GoalResource($goal),
            'goalTypes' => $this->getGoalTypeOptions(),
            'goalStatuses' => $this->getGoalStatusOptions(),
            'priorities' => $this->getPriorityOptions(),
            'metricTypes' => $this->getMetricTypeOptions(),
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

    /**
     * Get goal type options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getGoalTypeOptions(): array
    {
        return array_map(
            fn (GoalType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'color' => $type->colorClass(),
            ],
            GoalType::cases()
        );
    }

    /**
     * Get goal status options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getGoalStatusOptions(): array
    {
        return array_map(
            fn (GoalStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->colorClass(),
            ],
            GoalStatus::cases()
        );
    }

    /**
     * Get priority options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getPriorityOptions(): array
    {
        return array_map(
            fn (GoalPriority $priority) => [
                'value' => $priority->value,
                'label' => $priority->label(),
                'description' => $priority->description(),
                'color' => $priority->colorClass(),
            ],
            GoalPriority::cases()
        );
    }

    /**
     * Get visibility options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getVisibilityOptions(): array
    {
        return array_map(
            fn (GoalVisibility $visibility) => [
                'value' => $visibility->value,
                'label' => $visibility->label(),
                'description' => $visibility->description(),
                'color' => $visibility->colorClass(),
            ],
            GoalVisibility::cases()
        );
    }

    /**
     * Get metric type options for key results.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function getMetricTypeOptions(): array
    {
        return array_map(
            fn (KeyResultMetricType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            KeyResultMetricType::cases()
        );
    }
}
