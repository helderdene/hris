<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EvaluationResponse model for storing reviewer feedback.
 *
 * Contains narrative feedback sections and links to individual
 * competency and KPI ratings. Supports draft mode for save-as-you-go.
 */
class EvaluationResponse extends TenantModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'evaluation_reviewer_id',
        'strengths',
        'areas_for_improvement',
        'overall_comments',
        'development_suggestions',
        'is_draft',
        'last_saved_at',
        'submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean',
            'last_saved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Get the evaluation reviewer this response belongs to.
     */
    public function evaluationReviewer(): BelongsTo
    {
        return $this->belongsTo(EvaluationReviewer::class);
    }

    /**
     * Get the competency ratings for this response.
     */
    public function competencyRatings(): HasMany
    {
        return $this->hasMany(EvaluationCompetencyRating::class);
    }

    /**
     * Get the KPI ratings for this response.
     */
    public function kpiRatings(): HasMany
    {
        return $this->hasMany(EvaluationKpiRating::class);
    }

    /**
     * Save as draft with current timestamp.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(array $data): bool
    {
        $data['is_draft'] = true;
        $data['last_saved_at'] = now();

        return $this->update($data);
    }

    /**
     * Submit the response (marks as final).
     */
    public function submit(): bool
    {
        return $this->update([
            'is_draft' => false,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Check if the response is complete (has required fields).
     */
    public function isComplete(): bool
    {
        // Basic narrative fields should have content
        $hasNarrative = ! empty($this->strengths)
            || ! empty($this->areas_for_improvement)
            || ! empty($this->overall_comments);

        // Check competency ratings
        $hasCompetencyRatings = $this->competencyRatings()
            ->whereNotNull('rating')
            ->exists();

        return $hasNarrative && $hasCompetencyRatings;
    }

    /**
     * Get the completion percentage based on filled fields.
     */
    public function getCompletionPercentage(): int
    {
        $total = 0;
        $filled = 0;

        // Narrative fields (4 fields, each worth 10%)
        $narrativeFields = ['strengths', 'areas_for_improvement', 'overall_comments', 'development_suggestions'];
        foreach ($narrativeFields as $field) {
            $total += 10;
            if (! empty($this->$field)) {
                $filled += 10;
            }
        }

        // Competency ratings (60%)
        $requiredCompetencies = $this->getRequiredCompetencyCount();
        if ($requiredCompetencies > 0) {
            $total += 60;
            $ratedCompetencies = $this->competencyRatings()->whereNotNull('rating')->count();
            $filled += (int) (60 * ($ratedCompetencies / $requiredCompetencies));
        }

        if ($total === 0) {
            return 0;
        }

        return min(100, (int) ($filled / $total * 100));
    }

    /**
     * Get the number of required competency ratings.
     */
    protected function getRequiredCompetencyCount(): int
    {
        $reviewer = $this->evaluationReviewer;
        if (! $reviewer) {
            return 0;
        }

        $participant = $reviewer->participant;
        if (! $participant) {
            return 0;
        }

        $employee = $participant->employee;
        if (! $employee || ! $employee->position_id) {
            return 0;
        }

        return PositionCompetency::forPosition($employee->position_id)
            ->withActiveCompetency()
            ->count();
    }

    /**
     * Get the average competency rating.
     */
    public function getAverageCompetencyRating(): ?float
    {
        $avg = $this->competencyRatings()
            ->whereNotNull('rating')
            ->avg('rating');

        return $avg ? round((float) $avg, 2) : null;
    }

    /**
     * Get the average KPI rating.
     */
    public function getAverageKpiRating(): ?float
    {
        $avg = $this->kpiRatings()
            ->whereNotNull('rating')
            ->avg('rating');

        return $avg ? round((float) $avg, 2) : null;
    }
}
