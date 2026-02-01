<?php

namespace App\Models;

use App\Enums\DevelopmentItemStatus;
use App\Enums\GoalPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DevelopmentPlanItem model for individual development areas.
 *
 * Represents a specific skill or competency to develop within a plan.
 * Can be linked to a competency for gap-based tracking.
 */
class DevelopmentPlanItem extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DevelopmentPlanItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'development_plan_id',
        'competency_id',
        'title',
        'description',
        'current_level',
        'target_level',
        'priority',
        'status',
        'progress_percentage',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => GoalPriority::class,
            'status' => DevelopmentItemStatus::class,
            'current_level' => 'integer',
            'target_level' => 'integer',
            'progress_percentage' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the development plan this item belongs to.
     */
    public function developmentPlan(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPlan::class);
    }

    /**
     * Get the competency this item is linked to (optional).
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /**
     * Get the activities for this item.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DevelopmentActivity::class)->orderBy('due_date');
    }

    /**
     * Scope to filter by priority.
     */
    public function scopeByPriority(Builder $query, GoalPriority|string $priority): Builder
    {
        $value = $priority instanceof GoalPriority ? $priority->value : $priority;

        return $query->where('priority', $value);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, DevelopmentItemStatus|string $status): Builder
    {
        $value = $status instanceof DevelopmentItemStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to get incomplete items.
     */
    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->whereNot('status', DevelopmentItemStatus::Completed);
    }

    /**
     * Update progress based on completed activities.
     */
    public function updateProgress(): void
    {
        $activities = $this->activities()->get();

        if ($activities->isEmpty()) {
            return;
        }

        $completedCount = $activities->where('is_completed', true)->count();
        $progress = (int) round(($completedCount / $activities->count()) * 100);

        $status = $this->status;
        if ($progress === 100) {
            $status = DevelopmentItemStatus::Completed;
        } elseif ($progress > 0) {
            $status = DevelopmentItemStatus::InProgress;
        }

        $this->update([
            'progress_percentage' => $progress,
            'status' => $status,
            'completed_at' => $progress === 100 ? now() : null,
        ]);
    }

    /**
     * Mark the item as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => DevelopmentItemStatus::Completed,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the proficiency gap (target level - current level).
     */
    public function getProficiencyGap(): ?int
    {
        if ($this->current_level === null || $this->target_level === null) {
            return null;
        }

        return $this->target_level - $this->current_level;
    }

    /**
     * Check if this item is linked to a competency.
     */
    public function hasCompetency(): bool
    {
        return $this->competency_id !== null;
    }
}
