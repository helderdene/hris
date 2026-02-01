<?php

namespace App\Models;

use App\Enums\PerformanceCycleInstanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PerformanceCycleInstance model for managing individual evaluation period instances.
 *
 * Each instance represents an actual performance evaluation period with specific
 * date ranges, status tracking, and participant assignments.
 */
class PerformanceCycleInstance extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PerformanceCycleInstanceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'performance_cycle_id',
        'name',
        'year',
        'instance_number',
        'start_date',
        'end_date',
        'status',
        'employee_count',
        'activated_at',
        'evaluation_started_at',
        'closed_at',
        'closed_by',
        'notes',
        'enable_360_feedback',
        'enable_peer_review',
        'enable_direct_report_review',
        'self_evaluation_deadline',
        'peer_review_deadline',
        'manager_review_deadline',
        'calibration_deadline',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'employee_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PerformanceCycleInstanceStatus::class,
            'year' => 'integer',
            'instance_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'employee_count' => 'integer',
            'activated_at' => 'datetime',
            'evaluation_started_at' => 'datetime',
            'closed_at' => 'datetime',
            'enable_360_feedback' => 'boolean',
            'enable_peer_review' => 'boolean',
            'enable_direct_report_review' => 'boolean',
            'self_evaluation_deadline' => 'date',
            'peer_review_deadline' => 'date',
            'manager_review_deadline' => 'date',
            'calibration_deadline' => 'date',
        ];
    }

    /**
     * Get the performance cycle this instance belongs to.
     */
    public function performanceCycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class);
    }

    /**
     * Get the user who closed this instance.
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the participants for this instance.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(PerformanceCycleParticipant::class);
    }

    /**
     * Scope to filter instances by year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter instances by status.
     */
    public function scopeByStatus(Builder $query, PerformanceCycleInstanceStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by performance cycle.
     */
    public function scopeForCycle(Builder $query, int $cycleId): Builder
    {
        return $query->where('performance_cycle_id', $cycleId);
    }

    /**
     * Scope to get instances that contain a specific date.
     */
    public function scopeContainingDate(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }

    /**
     * Check if the instance can transition to the given status.
     */
    public function canTransitionTo(PerformanceCycleInstanceStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    /**
     * Transition the instance to a new status.
     *
     * @throws \InvalidArgumentException If the transition is not allowed
     */
    public function transitionTo(PerformanceCycleInstanceStatus $newStatus): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->status->value} to {$newStatus->value}"
            );
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === PerformanceCycleInstanceStatus::Active && $this->activated_at === null) {
            $updates['activated_at'] = now();
        }

        if ($newStatus === PerformanceCycleInstanceStatus::InEvaluation && $this->evaluation_started_at === null) {
            $updates['evaluation_started_at'] = now();
        }

        if ($newStatus === PerformanceCycleInstanceStatus::Closed) {
            $updates['closed_at'] = now();
            $updates['closed_by'] = auth()->id();
        }

        $this->update($updates);
    }

    /**
     * Check if this instance can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if this instance can be deleted.
     */
    public function isDeletable(): bool
    {
        return $this->status->isDeletable();
    }

    /**
     * Get the date range as a formatted string.
     */
    public function getDateRange(): string
    {
        return $this->start_date->format('M j').' - '.$this->end_date->format('M j, Y');
    }

    /**
     * Update the employee count based on included participants.
     */
    public function updateEmployeeCount(): void
    {
        $count = $this->participants()
            ->where('is_excluded', false)
            ->count();

        $this->update(['employee_count' => $count]);
    }
}
