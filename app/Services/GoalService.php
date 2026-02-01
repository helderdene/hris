<?php

namespace App\Services;

use App\Enums\GoalApprovalStatus;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Models\GoalProgressEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GoalService
{
    /**
     * Create a new goal with optional key results or milestones.
     *
     * @param  array<string, mixed>  $data
     */
    public function createGoal(array $data): Goal
    {
        $requiresApproval = $data['requires_approval'] ?? false;

        $goal = Goal::create([
            'employee_id' => $data['employee_id'],
            'performance_cycle_instance_id' => $data['performance_cycle_instance_id'] ?? null,
            'parent_goal_id' => $data['parent_goal_id'] ?? null,
            'goal_type' => $data['goal_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'visibility' => $data['visibility'],
            'priority' => $data['priority'],
            'status' => GoalStatus::Draft,
            'approval_status' => $requiresApproval ? GoalApprovalStatus::NotRequired : GoalApprovalStatus::NotRequired,
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'weight' => $data['weight'] ?? 1.00,
            'owner_notes' => $data['owner_notes'] ?? null,
        ]);

        // Create key results for OKR type
        if ($goal->goal_type === GoalType::OkrObjective && ! empty($data['key_results'])) {
            $this->createKeyResults($goal, $data['key_results']);
        }

        // Create milestones for SMART type
        if ($goal->goal_type === GoalType::SmartGoal && ! empty($data['milestones'])) {
            $this->createMilestones($goal, $data['milestones']);
        }

        return $goal->fresh(['keyResults', 'milestones']);
    }

    /**
     * Update an existing goal.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGoal(Goal $goal, array $data): Goal
    {
        $goal->update([
            'parent_goal_id' => $data['parent_goal_id'] ?? $goal->parent_goal_id,
            'title' => $data['title'] ?? $goal->title,
            'description' => $data['description'] ?? $goal->description,
            'category' => $data['category'] ?? $goal->category,
            'visibility' => $data['visibility'] ?? $goal->visibility,
            'priority' => $data['priority'] ?? $goal->priority,
            'start_date' => $data['start_date'] ?? $goal->start_date,
            'due_date' => $data['due_date'] ?? $goal->due_date,
            'weight' => $data['weight'] ?? $goal->weight,
            'owner_notes' => $data['owner_notes'] ?? $goal->owner_notes,
        ]);

        return $goal->fresh();
    }

    /**
     * Create key results for a goal.
     *
     * @param  array<int, array<string, mixed>>  $keyResultsData
     */
    public function createKeyResults(Goal $goal, array $keyResultsData): void
    {
        foreach ($keyResultsData as $index => $krData) {
            $goal->keyResults()->create([
                'title' => $krData['title'],
                'description' => $krData['description'] ?? null,
                'metric_type' => $krData['metric_type'],
                'metric_unit' => $krData['metric_unit'] ?? null,
                'target_value' => $krData['target_value'],
                'starting_value' => $krData['starting_value'] ?? 0,
                'weight' => $krData['weight'] ?? 1.00,
                'sort_order' => $krData['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Create milestones for a goal.
     *
     * @param  array<int, array<string, mixed>>  $milestonesData
     */
    public function createMilestones(Goal $goal, array $milestonesData): void
    {
        foreach ($milestonesData as $index => $milestoneData) {
            $goal->milestones()->create([
                'title' => $milestoneData['title'],
                'description' => $milestoneData['description'] ?? null,
                'due_date' => $milestoneData['due_date'] ?? null,
                'sort_order' => $milestoneData['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Calculate and update goal progress.
     */
    public function calculateGoalProgress(Goal $goal): float
    {
        $progress = $goal->calculateProgress();

        $goal->recalculateParentGoalProgress();

        return $progress;
    }

    /**
     * Record progress for a key result.
     */
    public function recordKeyResultProgress(GoalKeyResult $keyResult, float $value, ?string $notes = null, ?User $user = null): GoalProgressEntry
    {
        return $keyResult->recordProgress($value, $notes, $user);
    }

    /**
     * Record manual progress update for a SMART goal.
     */
    public function recordGoalProgress(Goal $goal, float $progressPercentage, ?string $notes = null, ?User $user = null): GoalProgressEntry
    {
        $entry = $goal->progressEntries()->create([
            'progress_percentage' => $progressPercentage,
            'notes' => $notes,
            'recorded_at' => now(),
            'recorded_by' => $user?->id ?? auth()->id(),
        ]);

        $goal->update(['progress_percentage' => $progressPercentage]);

        $goal->recalculateParentGoalProgress();

        return $entry;
    }

    /**
     * Submit a goal for approval.
     */
    public function submitForApproval(Goal $goal): Goal
    {
        $goal->requestApproval();

        return $goal->fresh();
    }

    /**
     * Approve a goal.
     */
    public function approveGoal(Goal $goal, User $approver, ?string $feedback = null): Goal
    {
        $goal->approve($approver, $feedback);

        return $goal->fresh();
    }

    /**
     * Reject a goal.
     */
    public function rejectGoal(Goal $goal, User $approver, string $feedback): Goal
    {
        $goal->reject($approver, $feedback);

        return $goal->fresh();
    }

    /**
     * Complete a goal.
     */
    public function completeGoal(Goal $goal): Goal
    {
        $goal->markCompleted();

        return $goal->fresh();
    }

    /**
     * Cancel a goal.
     */
    public function cancelGoal(Goal $goal): Goal
    {
        $goal->update(['status' => GoalStatus::Cancelled]);

        return $goal->fresh();
    }

    /**
     * Align a goal to a parent goal.
     */
    public function alignGoalToParent(Goal $goal, Goal $parentGoal): Goal
    {
        $goal->update(['parent_goal_id' => $parentGoal->id]);

        return $goal->fresh(['parentGoal']);
    }

    /**
     * Remove alignment from a goal.
     */
    public function removeAlignment(Goal $goal): Goal
    {
        $goal->update(['parent_goal_id' => null]);

        return $goal->fresh();
    }

    /**
     * Get goal statistics for an employee.
     *
     * @return array<string, mixed>
     */
    public function getGoalStatistics(Employee $employee): array
    {
        $goals = Goal::forEmployee($employee->id)->get();

        return [
            'total' => $goals->count(),
            'draft' => $goals->where('status', GoalStatus::Draft)->count(),
            'pending_approval' => $goals->where('status', GoalStatus::PendingApproval)->count(),
            'active' => $goals->where('status', GoalStatus::Active)->count(),
            'completed' => $goals->where('status', GoalStatus::Completed)->count(),
            'cancelled' => $goals->where('status', GoalStatus::Cancelled)->count(),
            'overdue' => $goals->filter(fn ($g) => $g->isOverdue())->count(),
            'okrs' => $goals->where('goal_type', GoalType::OkrObjective)->count(),
            'smart_goals' => $goals->where('goal_type', GoalType::SmartGoal)->count(),
            'average_progress' => (float) ($goals->where('status', GoalStatus::Active)->avg('progress_percentage') ?? 0),
        ];
    }

    /**
     * Get team goal summary for a manager.
     *
     * @return array<string, mixed>
     */
    public function getTeamGoalSummary(Employee $manager): array
    {
        $subordinateIds = $manager->subordinates()->pluck('id');

        $teamGoals = Goal::whereIn('employee_id', $subordinateIds)->get();

        return [
            'total_team_goals' => $teamGoals->count(),
            'active_goals' => $teamGoals->where('status', GoalStatus::Active)->count(),
            'pending_approval' => $teamGoals->where('approval_status', GoalApprovalStatus::Pending)->count(),
            'completed_goals' => $teamGoals->where('status', GoalStatus::Completed)->count(),
            'overdue_goals' => $teamGoals->filter(fn ($g) => $g->isOverdue())->count(),
            'average_progress' => (float) ($teamGoals->where('status', GoalStatus::Active)->avg('progress_percentage') ?? 0),
            'by_employee' => $this->groupGoalsByEmployee($teamGoals),
        ];
    }

    /**
     * Group goals by employee.
     *
     * @param  Collection<int, Goal>  $goals
     * @return array<int, array<string, mixed>>
     */
    protected function groupGoalsByEmployee(Collection $goals): array
    {
        return $goals->groupBy('employee_id')
            ->map(function ($employeeGoals, $employeeId) {
                return [
                    'employee_id' => $employeeId,
                    'total' => $employeeGoals->count(),
                    'active' => $employeeGoals->where('status', GoalStatus::Active)->count(),
                    'completed' => $employeeGoals->where('status', GoalStatus::Completed)->count(),
                    'average_progress' => (float) ($employeeGoals->where('status', GoalStatus::Active)->avg('progress_percentage') ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get goals pending approval for a manager.
     */
    public function getPendingApprovals(Employee $manager): Collection
    {
        $subordinateIds = $manager->subordinates()->pluck('id');

        return Goal::whereIn('employee_id', $subordinateIds)
            ->where('approval_status', GoalApprovalStatus::Pending)
            ->with(['employee', 'keyResults', 'milestones'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get paginated goals with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getGoals(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Goal::query()
            ->with(['employee', 'keyResults', 'milestones']);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->whereHas('employee', function (Builder $q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (! empty($filters['goal_type'])) {
            $query->where('goal_type', $filters['goal_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (! empty($filters['performance_cycle_instance_id'])) {
            $query->where('performance_cycle_instance_id', $filters['performance_cycle_instance_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }
}
