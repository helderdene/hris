<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CompetencyEvaluation model for tracking competency assessments
 * within performance cycles.
 *
 * Supports self-rating by employees, manager rating, and final rating
 * determined by the manager.
 */
class CompetencyEvaluation extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CompetencyEvaluationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'performance_cycle_participant_id',
        'position_competency_id',
        'self_rating',
        'self_comments',
        'manager_rating',
        'manager_comments',
        'final_rating',
        'evidence',
        'evaluated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_rating' => 'integer',
            'manager_rating' => 'integer',
            'final_rating' => 'integer',
            'evidence' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    /**
     * Get the performance cycle participant this evaluation belongs to.
     */
    public function performanceCycleParticipant(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleParticipant::class);
    }

    /**
     * Get the position competency being evaluated.
     */
    public function positionCompetency(): BelongsTo
    {
        return $this->belongsTo(PositionCompetency::class);
    }

    /**
     * Scope to filter evaluations with self-rating.
     */
    public function scopeWithSelfRating(Builder $query): Builder
    {
        return $query->whereNotNull('self_rating');
    }

    /**
     * Scope to filter evaluations with manager rating.
     */
    public function scopeWithManagerRating(Builder $query): Builder
    {
        return $query->whereNotNull('manager_rating');
    }

    /**
     * Scope to filter evaluations with final rating.
     */
    public function scopeWithFinalRating(Builder $query): Builder
    {
        return $query->whereNotNull('final_rating');
    }

    /**
     * Scope to filter evaluations for a specific participant.
     */
    public function scopeForParticipant(Builder $query, int $participantId): Builder
    {
        return $query->where('performance_cycle_participant_id', $participantId);
    }

    /**
     * Check if the evaluation is complete (has final rating).
     */
    public function isComplete(): bool
    {
        return $this->final_rating !== null;
    }

    /**
     * Calculate the gap between self-rating and manager rating.
     *
     * Returns null if either rating is missing.
     */
    public function getRatingGap(): ?int
    {
        if ($this->self_rating === null || $this->manager_rating === null) {
            return null;
        }

        return $this->self_rating - $this->manager_rating;
    }

    /**
     * Get the gap from the required proficiency level.
     *
     * Returns null if final rating is missing.
     */
    public function getProficiencyGap(): ?int
    {
        if ($this->final_rating === null) {
            return null;
        }

        $requiredLevel = $this->positionCompetency?->required_proficiency_level;
        if ($requiredLevel === null) {
            return null;
        }

        return $this->final_rating - $requiredLevel;
    }
}
