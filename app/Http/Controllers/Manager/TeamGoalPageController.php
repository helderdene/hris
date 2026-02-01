<?php

namespace App\Http\Controllers\Manager;

use App\Enums\GoalApprovalStatus;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Http\Controllers\Controller;
use App\Http\Resources\GoalListResource;
use App\Models\Employee;
use App\Models\Goal;
use App\Services\GoalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamGoalPageController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * Display the team goals page.
     */
    public function index(Request $request): Response
    {
        $manager = $this->getCurrentManager($request);

        $subordinateIds = $manager->subordinates()->pluck('id');

        $query = Goal::whereIn('employee_id', $subordinateIds)
            ->with(['employee.department', 'keyResults', 'milestones'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('goal_type')) {
            $query->where('goal_type', $request->input('goal_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $goals = $query->paginate(15)->withQueryString();

        // Get team members for filter dropdown
        $teamMembers = $manager->subordinates()
            ->orderBy('last_name')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
            ]);

        // Get team summary
        $summary = $this->goalService->getTeamGoalSummary($manager);

        return Inertia::render('Manager/TeamGoals/Index', [
            'goals' => GoalListResource::collection($goals),
            'teamMembers' => $teamMembers,
            'summary' => $summary,
            'goalTypes' => $this->getGoalTypeOptions(),
            'goalStatuses' => $this->getGoalStatusOptions(),
            'filters' => [
                'employee_id' => $request->input('employee_id') ? (int) $request->input('employee_id') : null,
                'goal_type' => $request->input('goal_type'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Display the approval queue page.
     */
    public function approvals(Request $request): Response
    {
        $manager = $this->getCurrentManager($request);

        $pendingGoals = $this->goalService->getPendingApprovals($manager);

        // Get team members for context
        $teamMembers = $manager->subordinates()
            ->orderBy('last_name')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
            ]);

        return Inertia::render('Manager/TeamGoals/Approvals', [
            'pendingGoals' => GoalListResource::collection($pendingGoals),
            'teamMembers' => $teamMembers,
            'approvalStatuses' => $this->getApprovalStatusOptions(),
        ]);
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

    /**
     * Get goal type options.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function getGoalTypeOptions(): array
    {
        return array_map(
            fn (GoalType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'color' => $type->colorClass(),
            ],
            GoalType::cases()
        );
    }

    /**
     * Get goal status options.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function getGoalStatusOptions(): array
    {
        return array_map(
            fn (GoalStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->colorClass(),
            ],
            GoalStatus::cases()
        );
    }

    /**
     * Get approval status options.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function getApprovalStatusOptions(): array
    {
        return array_map(
            fn (GoalApprovalStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->colorClass(),
            ],
            GoalApprovalStatus::cases()
        );
    }
}
