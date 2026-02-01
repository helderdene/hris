<?php

namespace App\Models;

use App\Enums\KpiAssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KpiAssignment model for storing KPI assignments to performance cycle participants.
 *
 * Links a KPI template to a specific participant with individual targets,
 * weights, and progress tracking.
 */
class KpiAssignment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\KpiAssignmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kpi_template_id',
        'performance_cycle_participant_id',
        'target_value',
        'weight',
        'actual_value',
        'achievement_percentage',
        'status',
        'notes',
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
            'target_value' => 'decimal:2',
            'weight' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'achievement_percentage' => 'decimal:2',
            'status' => KpiAssignmentStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the KPI template for this assignment.
     */
    public function kpiTemplate(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class);
    }

    /**
     * Get the participant this KPI is assigned to.
     */
    public function performanceCycleParticipant(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleParticipant::class);
    }

    /**
     * Get the progress entries for this assignment.
     */
    public function progressEntries(): HasMany
    {
        return $this->hasMany(KpiProgressEntry::class);
    }

    /**
     * Scope to filter pending assignments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', KpiAssignmentStatus::Pending);
    }

    /**
     * Scope to filter in-progress assignments.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', KpiAssignmentStatus::InProgress);
    }

    /**
     * Scope to filter completed assignments.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', KpiAssignmentStatus::Completed);
    }

    /**
     * Scope to filter by participant.
     */
    public function scopeForParticipant(Builder $query, int $participantId): Builder
    {
        return $query->where('performance_cycle_participant_id', $participantId);
    }

    /**
     * Record a progress entry for this assignment.
     */
    public function recordProgress(float $value, ?string $notes = null, ?User $user = null): KpiProgressEntry
    {
        $entry = $this->progressEntries()->create([
            'value' => $value,
            'notes' => $notes,
            'recorded_at' => now(),
            'recorded_by' => $user?->id ?? auth()->id(),
        ]);

        // Update actual value to the latest progress
        $this->update([
            'actual_value' => $value,
            'status' => KpiAssignmentStatus::InProgress,
        ]);

        // Recalculate achievement
        $this->calculateAchievement();

        return $entry;
    }

    /**
     * Calculate and update the achievement percentage.
     */
    public function calculateAchievement(): float
    {
        if ($this->target_value == 0) {
            $achievement = $this->actual_value > 0 ? 100 : 0;
        } else {
            $achievement = ($this->actual_value / $this->target_value) * 100;
        }

        // Cap at 200% to handle over-achievement reasonably
        $achievement = min($achievement, 200);

        $this->update(['achievement_percentage' => $achievement]);

        return $achievement;
    }

    /**
     * Mark the assignment as completed.
     */
    public function markCompleted(): void
    {
        $this->calculateAchievement();

        $this->update([
            'status' => KpiAssignmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the latest progress entry.
     */
    public function getLatestProgress(): ?KpiProgressEntry
    {
        return $this->progressEntries()
            ->orderBy('recorded_at', 'desc')
            ->first();
    }
}
