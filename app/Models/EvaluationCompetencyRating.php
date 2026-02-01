<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EvaluationCompetencyRating model for individual competency ratings.
 *
 * Stores the rating (1-5) and optional comments for a specific
 * position competency within an evaluation response.
 */
class EvaluationCompetencyRating extends TenantModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'evaluation_response_id',
        'position_competency_id',
        'rating',
        'comments',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Get the evaluation response this rating belongs to.
     */
    public function evaluationResponse(): BelongsTo
    {
        return $this->belongsTo(EvaluationResponse::class);
    }

    /**
     * Get the position competency being rated.
     */
    public function positionCompetency(): BelongsTo
    {
        return $this->belongsTo(PositionCompetency::class);
    }

    /**
     * Check if the rating meets the required proficiency level.
     */
    public function meetsRequirement(): ?bool
    {
        if ($this->rating === null) {
            return null;
        }

        $required = $this->positionCompetency?->required_proficiency_level;
        if ($required === null) {
            return null;
        }

        return $this->rating >= $required;
    }

    /**
     * Get the gap from the required proficiency level.
     */
    public function getProficiencyGap(): ?int
    {
        if ($this->rating === null) {
            return null;
        }

        $required = $this->positionCompetency?->required_proficiency_level;
        if ($required === null) {
            return null;
        }

        return $this->rating - $required;
    }
}
