<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GoalMilestone model for storing milestones associated with SMART-type goals.
 */
class GoalMilestone extends TenantModel
{
    /** @use HasFactory<\Database\Factories\GoalMilestoneFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'goal_id',
        'title',
        'description',
        'due_date',
        'is_completed',
        'completed_at',
        'completed_by',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the goal this milestone belongs to.
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Get the user who completed this milestone.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Mark the milestone as completed.
     */
    public function markCompleted(?User $user = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $user?->id ?? auth()->id(),
        ]);

        $this->goal->calculateProgress();
    }

    /**
     * Unmark the milestone as completed.
     */
    public function unmarkCompleted(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);

        $this->goal->calculateProgress();
    }

    /**
     * Toggle the completion status.
     */
    public function toggleComplete(?User $user = null): void
    {
        if ($this->is_completed) {
            $this->unmarkCompleted();
        } else {
            $this->markCompleted($user);
        }
    }

    /**
     * Check if the milestone is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->is_completed || $this->due_date === null) {
            return false;
        }

        return $this->due_date->isPast();
    }
}
