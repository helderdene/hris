<?php

namespace App\Models;

use App\Enums\DevelopmentPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DevelopmentPlan model for individual development plans.
 *
 * Plans can be created standalone or linked to evaluation results.
 * Follows a collaborative model where employee creates and manager approves.
 */
class DevelopmentPlan extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DevelopmentPlanFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'performance_cycle_participant_id',
        'title',
        'description',
        'status',
        'start_date',
        'target_completion_date',
        'completed_at',
        'career_path_notes',
        'manager_id',
        'approved_by',
        'approved_at',
        'approval_notes',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DevelopmentPlanStatus::class,
            'start_date' => 'date',
            'target_completion_date' => 'date',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this plan belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the performance cycle participant this plan is linked to (optional).
     */
    public function performanceCycleParticipant(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleParticipant::class);
    }

    /**
     * Get the manager assigned to review this plan.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get the user who approved this plan.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who created this plan.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the development items for this plan.
     */
    public function items(): HasMany
    {
        return $this->hasMany(DevelopmentPlanItem::class)->orderBy('priority');
    }

    /**
     * Get the check-ins for this plan.
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(DevelopmentPlanCheckIn::class)->orderBy('check_in_date', 'desc');
    }

    /**
     * Scope to filter plans for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter pending approval plans.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DevelopmentPlanStatus::PendingApproval);
    }

    /**
     * Scope to filter active plans (approved or in progress).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DevelopmentPlanStatus::Approved,
            DevelopmentPlanStatus::InProgress,
        ]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, DevelopmentPlanStatus|string $status): Builder
    {
        $value = $status instanceof DevelopmentPlanStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to filter plans managed by a specific employee.
     */
    public function scopeManagedBy(Builder $query, int $managerId): Builder
    {
        return $query->where('manager_id', $managerId);
    }

    /**
     * Submit the plan for manager approval.
     */
    public function submit(): void
    {
        $this->update([
            'status' => DevelopmentPlanStatus::PendingApproval,
        ]);
    }

    /**
     * Approve the plan.
     */
    public function approve(User $approver, ?string $notes = null): void
    {
        $this->update([
            'status' => DevelopmentPlanStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Reject the plan and return to draft.
     */
    public function reject(User $approver, ?string $notes = null): void
    {
        $this->update([
            'status' => DevelopmentPlanStatus::Draft,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Start the development plan.
     */
    public function start(): void
    {
        $this->update([
            'status' => DevelopmentPlanStatus::InProgress,
            'start_date' => $this->start_date ?? now()->toDateString(),
        ]);
    }

    /**
     * Mark the plan as completed.
     */
    public function complete(): void
    {
        $this->update([
            'status' => DevelopmentPlanStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel the plan.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => DevelopmentPlanStatus::Cancelled,
        ]);
    }

    /**
     * Calculate the overall progress percentage based on items.
     */
    public function calculateProgress(): float
    {
        $items = $this->items()->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $totalProgress = $items->sum('progress_percentage');

        return round($totalProgress / $items->count(), 2);
    }

    /**
     * Check if the plan can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if activities can be added to this plan.
     */
    public function canAddActivities(): bool
    {
        return $this->status->canAddActivities();
    }

    /**
     * Check if the plan is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        if ($this->target_completion_date === null) {
            return false;
        }

        return $this->target_completion_date->isPast();
    }

    /**
     * Get the number of days remaining until target completion.
     */
    public function getDaysRemaining(): ?int
    {
        if ($this->target_completion_date === null) {
            return null;
        }

        if ($this->target_completion_date->isPast()) {
            return 0;
        }

        return now()->diffInDays($this->target_completion_date);
    }
}
