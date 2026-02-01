<?php

namespace App\Models;

use App\Enums\DevelopmentActivityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DevelopmentActivity model for tracking specific development activities.
 *
 * Activities are concrete actions within a development plan item,
 * such as courses, projects, or mentoring sessions.
 */
class DevelopmentActivity extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DevelopmentActivityFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'development_plan_item_id',
        'activity_type',
        'title',
        'description',
        'resource_url',
        'due_date',
        'is_completed',
        'completed_at',
        'completion_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => DevelopmentActivityType::class,
            'due_date' => 'date',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the development plan item this activity belongs to.
     */
    public function developmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPlanItem::class);
    }

    /**
     * Scope to filter by activity type.
     */
    public function scopeByType(Builder $query, DevelopmentActivityType|string $type): Builder
    {
        $value = $type instanceof DevelopmentActivityType ? $type->value : $type;

        return $query->where('activity_type', $value);
    }

    /**
     * Scope to get completed activities.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope to get incomplete activities.
     */
    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope to get overdue activities.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('is_completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
    }

    /**
     * Mark the activity as completed.
     */
    public function markCompleted(?string $notes = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completion_notes' => $notes,
        ]);

        // Update parent item progress
        $this->developmentPlanItem->updateProgress();
    }

    /**
     * Mark the activity as incomplete.
     */
    public function markIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'completion_notes' => null,
        ]);

        // Update parent item progress
        $this->developmentPlanItem->updateProgress();
    }

    /**
     * Check if the activity is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->is_completed) {
            return false;
        }

        if ($this->due_date === null) {
            return false;
        }

        return $this->due_date->isPast();
    }

    /**
     * Get the number of days until due date.
     */
    public function getDaysUntilDue(): ?int
    {
        if ($this->due_date === null) {
            return null;
        }

        if ($this->due_date->isPast()) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }
}
