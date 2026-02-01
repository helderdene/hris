<?php

namespace App\Models;

use App\Enums\GoalApprovalStatus;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\GoalVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Goal model for storing both OKR objectives and SMART goals.
 *
 * Uses a discriminator column (goal_type) to distinguish between goal types.
 * Supports hierarchical alignment through parent_goal_id.
 */
class Goal extends TenantModel
{
    /** @use HasFactory<\Database\Factories\GoalFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'performance_cycle_instance_id',
        'parent_goal_id',
        'goal_type',
        'title',
        'description',
        'category',
        'visibility',
        'priority',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'start_date',
        'due_date',
        'completed_at',
        'progress_percentage',
        'weight',
        'final_score',
        'owner_notes',
        'manager_feedback',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goal_type' => GoalType::class,
            'visibility' => GoalVisibility::class,
            'priority' => GoalPriority::class,
            'status' => GoalStatus::class,
            'approval_status' => GoalApprovalStatus::class,
            'approved_at' => 'datetime',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress_percentage' => 'decimal:2',
            'weight' => 'decimal:2',
            'final_score' => 'decimal:2',
        ];
    }

    /**
     * Get the employee who owns this goal.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the performance cycle instance this goal is linked to.
     */
    public function performanceCycleInstance(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleInstance::class);
    }

    /**
     * Get the parent goal this goal is aligned to.
     */
    public function parentGoal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'parent_goal_id');
    }

    /**
     * Get the child goals aligned to this goal.
     */
    public function childGoals(): HasMany
    {
        return $this->hasMany(Goal::class, 'parent_goal_id');
    }

    /**
     * Get the key results for this goal (OKR type).
     */
    public function keyResults(): HasMany
    {
        return $this->hasMany(GoalKeyResult::class)->orderBy('sort_order');
    }

    /**
     * Get the milestones for this goal (SMART type).
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(GoalMilestone::class)->orderBy('sort_order');
    }

    /**
     * Get the progress entries for this goal.
     */
    public function progressEntries(): HasMany
    {
        return $this->hasMany(GoalProgressEntry::class)->orderBy('recorded_at', 'desc');
    }

    /**
     * Get the comments for this goal.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(GoalComment::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the user who approved this goal.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope to filter goals for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter goals for a specific cycle instance.
     */
    public function scopeForCycleInstance(Builder $query, int $cycleInstanceId): Builder
    {
        return $query->where('performance_cycle_instance_id', $cycleInstanceId);
    }

    /**
     * Scope to filter active goals.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GoalStatus::Active);
    }

    /**
     * Scope to filter OKR objectives.
     */
    public function scopeOkrs(Builder $query): Builder
    {
        return $query->where('goal_type', GoalType::OkrObjective);
    }

    /**
     * Scope to filter SMART goals.
     */
    public function scopeSmartGoals(Builder $query): Builder
    {
        return $query->where('goal_type', GoalType::SmartGoal);
    }

    /**
     * Scope to filter root-level goals (no parent).
     */
    public function scopeRootGoals(Builder $query): Builder
    {
        return $query->whereNull('parent_goal_id');
    }

    /**
     * Scope to filter goals pending approval.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', GoalApprovalStatus::Pending);
    }

    /**
     * Scope to filter goals visible to an employee.
     */
    public function scopeVisibleToEmployee(Builder $query, Employee $employee): Builder
    {
        return $query->where(function (Builder $q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('visibility', GoalVisibility::Organization)
                ->orWhere(function (Builder $q2) use ($employee) {
                    $q2->where('visibility', GoalVisibility::Team)
                        ->whereHas('employee', function (Builder $q3) use ($employee) {
                            $q3->where('department_id', $employee->department_id);
                        });
                });
        });
    }

    /**
     * Check if this is an OKR objective.
     */
    public function isOkr(): bool
    {
        return $this->goal_type === GoalType::OkrObjective;
    }

    /**
     * Check if this is a SMART goal.
     */
    public function isSmartGoal(): bool
    {
        return $this->goal_type === GoalType::SmartGoal;
    }

    /**
     * Calculate and update the progress percentage.
     */
    public function calculateProgress(): float
    {
        if ($this->isOkr()) {
            return $this->calculateOkrProgress();
        }

        return $this->calculateSmartProgress();
    }

    /**
     * Calculate progress for OKR objectives based on key results.
     */
    protected function calculateOkrProgress(): float
    {
        $keyResults = $this->keyResults()->get();

        if ($keyResults->isEmpty()) {
            return 0;
        }

        $totalWeight = $keyResults->sum('weight');

        if ($totalWeight == 0) {
            return 0;
        }

        $weightedSum = $keyResults
            ->filter(fn ($kr) => $kr->achievement_percentage !== null)
            ->sum(fn ($kr) => ($kr->achievement_percentage ?? 0) * $kr->weight);

        $progress = min($weightedSum / $totalWeight, 100);

        $this->update(['progress_percentage' => $progress]);

        return $progress;
    }

    /**
     * Calculate progress for SMART goals based on milestones.
     */
    protected function calculateSmartProgress(): float
    {
        $milestones = $this->milestones()->get();

        if ($milestones->isEmpty()) {
            return $this->progress_percentage ?? 0;
        }

        $completedCount = $milestones->where('is_completed', true)->count();
        $progress = ($completedCount / $milestones->count()) * 100;

        $this->update(['progress_percentage' => $progress]);

        return $progress;
    }

    /**
     * Recalculate parent goal progress when this goal's progress changes.
     */
    public function recalculateParentGoalProgress(): void
    {
        if ($this->parent_goal_id === null) {
            return;
        }

        $parent = $this->parentGoal;
        if ($parent === null) {
            return;
        }

        $childGoals = $parent->childGoals()->get();

        if ($childGoals->isEmpty()) {
            return;
        }

        $totalWeight = $childGoals->sum('weight');

        if ($totalWeight == 0) {
            return;
        }

        $weightedSum = $childGoals->sum(fn ($goal) => ($goal->progress_percentage ?? 0) * $goal->weight);
        $progress = $weightedSum / $totalWeight;

        $parent->update(['progress_percentage' => $progress]);

        $parent->recalculateParentGoalProgress();
    }

    /**
     * Mark the goal as completed.
     */
    public function markCompleted(): void
    {
        $this->calculateProgress();

        $this->update([
            'status' => GoalStatus::Completed,
            'completed_at' => now(),
            'final_score' => $this->progress_percentage,
        ]);

        $this->recalculateParentGoalProgress();
    }

    /**
     * Submit the goal for approval.
     */
    public function requestApproval(): void
    {
        $this->update([
            'status' => GoalStatus::PendingApproval,
            'approval_status' => GoalApprovalStatus::Pending,
        ]);
    }

    /**
     * Approve the goal.
     */
    public function approve(User $approver, ?string $feedback = null): void
    {
        $this->update([
            'status' => GoalStatus::Active,
            'approval_status' => GoalApprovalStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'manager_feedback' => $feedback,
        ]);
    }

    /**
     * Reject the goal.
     */
    public function reject(User $approver, ?string $feedback = null): void
    {
        $this->update([
            'status' => GoalStatus::Draft,
            'approval_status' => GoalApprovalStatus::Rejected,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'manager_feedback' => $feedback,
        ]);
    }

    /**
     * Check if the goal is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status === GoalStatus::Completed || $this->status === GoalStatus::Cancelled) {
            return false;
        }

        return $this->due_date->isPast();
    }

    /**
     * Get the number of days remaining until the due date.
     */
    public function getDaysRemaining(): int
    {
        if ($this->due_date->isPast()) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }
}
