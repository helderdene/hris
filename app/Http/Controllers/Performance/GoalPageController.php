<?php

namespace App\Http\Controllers\Performance;

use App\Enums\GoalApprovalStatus;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\GoalVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\GoalListResource;
use App\Http\Resources\GoalResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceCycleInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GoalPageController extends Controller
{
    /**
     * Display the goals management page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = Goal::query()
            ->with(['employee.department', 'employee.position', 'keyResults', 'milestones'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        if ($request->filled('goal_type')) {
            $query->where('goal_type', $request->input('goal_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->input('approval_status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $goals = $query->paginate(15)->withQueryString();

        // Calculate statistics
        $statistics = [
            'total_goals' => Goal::count(),
            'okrs' => Goal::where('goal_type', GoalType::OkrObjective)->count(),
            'smart_goals' => Goal::where('goal_type', GoalType::SmartGoal)->count(),
            'active_goals' => Goal::where('status', GoalStatus::Active)->count(),
            'completed_goals' => Goal::where('status', GoalStatus::Completed)->count(),
            'overdue_goals' => Goal::where('status', GoalStatus::Active)
                ->where('due_date', '<', now())
                ->count(),
            'average_progress' => (float) (Goal::where('status', GoalStatus::Active)->avg('progress_percentage') ?? 0),
        ];

        // Get departments for filter dropdown
        $departments = Department::orderBy('name')->get()->map(fn ($dept) => [
            'id' => $dept->id,
            'name' => $dept->name,
        ]);

        // Get employees for filter dropdown
        $employees = Employee::active()
            ->orderBy('last_name')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
            ]);

        // Get active cycle instances
        $cycleInstances = PerformanceCycleInstance::query()
            ->with('performanceCycle')
            ->whereIn('status', ['active', 'in_evaluation'])
            ->orderBy('year', 'desc')
            ->get()
            ->map(fn ($instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'cycle_name' => $instance->performanceCycle?->name,
            ]);

        return Inertia::render('Performance/Goals/Index', [
            'goals' => GoalListResource::collection($goals),
            'statistics' => $statistics,
            'departments' => $departments,
            'employees' => $employees,
            'cycleInstances' => $cycleInstances,
            'goalTypes' => $this->getGoalTypeOptions(),
            'goalStatuses' => $this->getGoalStatusOptions(),
            'approvalStatuses' => $this->getApprovalStatusOptions(),
            'priorities' => $this->getPriorityOptions(),
            'visibilityOptions' => $this->getVisibilityOptions(),
            'filters' => [
                'employee_id' => $request->input('employee_id') ? (int) $request->input('employee_id') : null,
                'department_id' => $request->input('department_id') ? (int) $request->input('department_id') : null,
                'goal_type' => $request->input('goal_type'),
                'status' => $request->input('status'),
                'approval_status' => $request->input('approval_status'),
                'search' => $request->input('search'),
            ],
        ]);
    }

    /**
     * Display a single goal.
     */
    public function show(string $tenant, Goal $goal): Response
    {
        Gate::authorize('can-manage-organization');

        $goal->load([
            'employee.department',
            'employee.position',
            'parentGoal',
            'childGoals',
            'keyResults.progressEntries.recordedByUser',
            'milestones.completedByUser',
            'progressEntries.recordedByUser',
            'comments.user',
            'approvedByUser',
        ]);

        return Inertia::render('Performance/Goals/Show', [
            'goal' => new GoalResource($goal),
            'goalTypes' => $this->getGoalTypeOptions(),
            'goalStatuses' => $this->getGoalStatusOptions(),
            'approvalStatuses' => $this->getApprovalStatusOptions(),
            'priorities' => $this->getPriorityOptions(),
        ]);
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
     * Get approval status options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getApprovalStatusOptions(): array
    {
        return array_map(
            fn (GoalApprovalStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->colorClass(),
            ],
            GoalApprovalStatus::cases()
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
}
