<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * PerformanceCycleParticipant model for managing employee assignments
 * to performance cycle instances.
 *
 * Links employees with their reviewing managers for a specific evaluation period.
 */
class PerformanceCycleParticipant extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PerformanceCycleParticipantFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'performance_cycle_instance_id',
        'employee_id',
        'manager_id',
        'is_excluded',
        'status',
        'completed_at',
        'evaluation_status',
        'self_evaluation_due_date',
        'peer_review_due_date',
        'manager_review_due_date',
        'min_peer_reviewers',
        'max_peer_reviewers',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_excluded' => 'boolean',
            'completed_at' => 'datetime',
            'evaluation_status' => EvaluationStatus::class,
            'self_evaluation_due_date' => 'date',
            'peer_review_due_date' => 'date',
            'manager_review_due_date' => 'date',
            'min_peer_reviewers' => 'integer',
            'max_peer_reviewers' => 'integer',
        ];
    }

    /**
     * Get the performance cycle instance this participant belongs to.
     */
    public function performanceCycleInstance(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleInstance::class);
    }

    /**
     * Get the employee being evaluated.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the manager who will evaluate the employee.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get the KPI assignments for this participant.
     */
    public function kpiAssignments(): HasMany
    {
        return $this->hasMany(KpiAssignment::class);
    }

    /**
     * Get the competency evaluations for this participant.
     */
    public function competencyEvaluations(): HasMany
    {
        return $this->hasMany(CompetencyEvaluation::class);
    }

    /**
     * Get the goals for this participant within this performance cycle.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'employee_id', 'employee_id')
            ->where('performance_cycle_instance_id', $this->performance_cycle_instance_id);
    }

    /**
     * Get the evaluation reviewers for this participant.
     */
    public function evaluationReviewers(): HasMany
    {
        return $this->hasMany(EvaluationReviewer::class);
    }

    /**
     * Get the evaluation summary for this participant.
     */
    public function evaluationSummary(): HasOne
    {
        return $this->hasOne(EvaluationSummary::class);
    }

    /**
     * Get a summary of goals for this participant.
     *
     * @return array{total_goals: int, completed_goals: int, average_progress: float}
     */
    public function goalSummary(): array
    {
        $goals = $this->goals;

        return [
            'total_goals' => $goals->count(),
            'completed_goals' => $goals->where('status', \App\Enums\GoalStatus::Completed)->count(),
            'average_progress' => $goals->avg('progress_percentage') ?? 0,
        ];
    }

    /**
     * Calculate the weighted KPI achievement summary for this participant.
     *
     * @return array{total_kpis: int, completed_kpis: int, weighted_achievement: float|null}
     */
    public function kpiSummary(): array
    {
        $assignments = $this->kpiAssignments()->get();

        if ($assignments->isEmpty()) {
            return [
                'total_kpis' => 0,
                'completed_kpis' => 0,
                'weighted_achievement' => null,
            ];
        }

        $totalWeight = $assignments->sum('weight');
        $completedCount = $assignments->where('status', 'completed')->count();

        if ($totalWeight == 0) {
            return [
                'total_kpis' => $assignments->count(),
                'completed_kpis' => $completedCount,
                'weighted_achievement' => null,
            ];
        }

        $weightedSum = $assignments
            ->filter(fn ($a) => $a->achievement_percentage !== null)
            ->sum(fn ($a) => $a->achievement_percentage * $a->weight);

        $weightedAchievement = $weightedSum / $totalWeight;

        return [
            'total_kpis' => $assignments->count(),
            'completed_kpis' => $completedCount,
            'weighted_achievement' => round($weightedAchievement, 2),
        ];
    }

    /**
     * Scope to filter only included participants (not excluded).
     */
    public function scopeIncluded(Builder $query): Builder
    {
        return $query->where('is_excluded', false);
    }

    /**
     * Scope to filter only excluded participants.
     */
    public function scopeExcluded(Builder $query): Builder
    {
        return $query->where('is_excluded', true);
    }

    /**
     * Scope to filter pending participants.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter completed participants.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Mark the participant as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Scope to filter by evaluation status.
     */
    public function scopeByEvaluationStatus(Builder $query, EvaluationStatus|string $status): Builder
    {
        $value = $status instanceof EvaluationStatus ? $status->value : $status;

        return $query->where('evaluation_status', $value);
    }

    /**
     * Get or create the evaluation summary for this participant.
     */
    public function getOrCreateEvaluationSummary(): EvaluationSummary
    {
        return $this->evaluationSummary ?? $this->evaluationSummary()->create([
            'performance_cycle_participant_id' => $this->id,
        ]);
    }
}
