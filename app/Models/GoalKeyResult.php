<?php

namespace App\Models;

use App\Enums\KeyResultMetricType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GoalKeyResult model for storing measurable key results
 * associated with OKR-type goals.
 */
class GoalKeyResult extends TenantModel
{
    /** @use HasFactory<\Database\Factories\GoalKeyResultFactory> */
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
        'metric_type',
        'metric_unit',
        'target_value',
        'starting_value',
        'current_value',
        'achievement_percentage',
        'weight',
        'status',
        'completed_at',
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
            'metric_type' => KeyResultMetricType::class,
            'target_value' => 'decimal:2',
            'starting_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'achievement_percentage' => 'decimal:2',
            'weight' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the goal this key result belongs to.
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Get the progress entries for this key result.
     */
    public function progressEntries(): HasMany
    {
        return $this->hasMany(GoalProgressEntry::class)->orderBy('recorded_at', 'desc');
    }

    /**
     * Record a progress update for this key result.
     */
    public function recordProgress(float $value, ?string $notes = null, ?User $user = null): GoalProgressEntry
    {
        $entry = $this->progressEntries()->create([
            'goal_id' => $this->goal_id,
            'progress_value' => $value,
            'notes' => $notes,
            'recorded_at' => now(),
            'recorded_by' => $user?->id ?? auth()->id(),
        ]);

        $this->update([
            'current_value' => $value,
            'status' => 'in_progress',
        ]);

        $this->calculateAchievement();

        $this->goal->calculateProgress();

        return $entry;
    }

    /**
     * Calculate and update the achievement percentage.
     */
    public function calculateAchievement(): float
    {
        if ($this->metric_type === KeyResultMetricType::Boolean) {
            $achievement = $this->current_value >= 1 ? 100 : 0;
        } else {
            $range = $this->target_value - $this->starting_value;

            if ($range == 0) {
                $achievement = $this->current_value >= $this->target_value ? 100 : 0;
            } else {
                $progress = $this->current_value - $this->starting_value;
                $achievement = ($progress / $range) * 100;
            }
        }

        $achievement = max(0, min($achievement, 100));

        $this->update(['achievement_percentage' => $achievement]);

        return $achievement;
    }

    /**
     * Mark the key result as completed.
     */
    public function markCompleted(): void
    {
        $this->calculateAchievement();

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->goal->calculateProgress();
    }

    /**
     * Get a formatted display of the current value.
     */
    public function getFormattedCurrentValue(): string
    {
        if ($this->current_value === null) {
            return '-';
        }

        return $this->metric_type->formatValue($this->current_value, $this->metric_unit);
    }

    /**
     * Get a formatted display of the target value.
     */
    public function getFormattedTargetValue(): string
    {
        return $this->metric_type->formatValue($this->target_value, $this->metric_unit);
    }
}
