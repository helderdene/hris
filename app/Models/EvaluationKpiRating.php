<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EvaluationKpiRating model for individual KPI ratings.
 *
 * Stores the rating (1-5) and optional comments for a specific
 * KPI assignment within an evaluation response.
 * Only used by self and manager reviewers.
 */
class EvaluationKpiRating extends TenantModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'evaluation_response_id',
        'kpi_assignment_id',
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
     * Get the KPI assignment being rated.
     */
    public function kpiAssignment(): BelongsTo
    {
        return $this->belongsTo(KpiAssignment::class);
    }

    /**
     * Get the achievement percentage from the KPI assignment.
     */
    public function getAchievementPercentage(): ?float
    {
        return $this->kpiAssignment?->achievement_percentage;
    }

    /**
     * Check if the KPI was achieved (achievement >= 100%).
     */
    public function isKpiAchieved(): ?bool
    {
        $achievement = $this->getAchievementPercentage();

        return $achievement !== null ? $achievement >= 100 : null;
    }
}
