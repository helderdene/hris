<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EvaluationSummary model for storing aggregated evaluation scores.
 *
 * Contains averaged competency scores by reviewer type, KPI achievement,
 * final calibrated scores, and employee acknowledgement status.
 */
class EvaluationSummary extends TenantModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'performance_cycle_participant_id',
        'self_competency_avg',
        'manager_competency_avg',
        'peer_competency_avg',
        'direct_report_competency_avg',
        'overall_competency_avg',
        'kpi_achievement_score',
        'manager_kpi_rating',
        'final_competency_score',
        'final_kpi_score',
        'final_overall_score',
        'final_rating',
        'calibrated_at',
        'calibrated_by',
        'calibration_notes',
        'employee_acknowledged_at',
        'employee_comments',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_competency_avg' => 'decimal:2',
            'manager_competency_avg' => 'decimal:2',
            'peer_competency_avg' => 'decimal:2',
            'direct_report_competency_avg' => 'decimal:2',
            'overall_competency_avg' => 'decimal:2',
            'kpi_achievement_score' => 'decimal:2',
            'manager_kpi_rating' => 'integer',
            'final_competency_score' => 'decimal:2',
            'final_kpi_score' => 'decimal:2',
            'final_overall_score' => 'decimal:2',
            'calibrated_at' => 'datetime',
            'employee_acknowledged_at' => 'datetime',
        ];
    }

    /**
     * Get the participant this summary belongs to.
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleParticipant::class, 'performance_cycle_participant_id');
    }

    /**
     * Get the user who calibrated the scores.
     */
    public function calibratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calibrated_by');
    }

    /**
     * Calculate and update competency averages from submitted responses.
     */
    public function calculateAverages(): self
    {
        $participant = $this->participant;
        $reviewers = $participant->evaluationReviewers()->submitted()->get();

        $averages = [
            'self' => null,
            'manager' => null,
            'peer' => [],
            'direct_report' => [],
        ];

        foreach ($reviewers as $reviewer) {
            $response = $reviewer->evaluationResponse;
            if (! $response) {
                continue;
            }

            $avg = $response->getAverageCompetencyRating();
            if ($avg === null) {
                continue;
            }

            $type = $reviewer->reviewer_type->value;
            if (in_array($type, ['peer', 'direct_report'])) {
                $averages[$type][] = $avg;
            } else {
                $averages[$type] = $avg;
            }
        }

        // Calculate peer average
        $peerAvg = count($averages['peer']) > 0
            ? round(array_sum($averages['peer']) / count($averages['peer']), 2)
            : null;

        // Calculate direct report average
        $drAvg = count($averages['direct_report']) > 0
            ? round(array_sum($averages['direct_report']) / count($averages['direct_report']), 2)
            : null;

        // Calculate overall average
        $allAverages = array_filter([
            $averages['self'],
            $averages['manager'],
            $peerAvg,
            $drAvg,
        ], fn ($v) => $v !== null);

        $overallAvg = count($allAverages) > 0
            ? round(array_sum($allAverages) / count($allAverages), 2)
            : null;

        $this->update([
            'self_competency_avg' => $averages['self'],
            'manager_competency_avg' => $averages['manager'],
            'peer_competency_avg' => $peerAvg,
            'direct_report_competency_avg' => $drAvg,
            'overall_competency_avg' => $overallAvg,
        ]);

        return $this->fresh();
    }

    /**
     * Calculate KPI achievement score from assignments.
     */
    public function calculateKpiScore(): self
    {
        $participant = $this->participant;
        $kpiAssignments = $participant->kpiAssignments;

        if ($kpiAssignments->isEmpty()) {
            return $this;
        }

        // Weighted average of achievement percentages
        $totalWeight = $kpiAssignments->sum('weight');
        if ($totalWeight <= 0) {
            return $this;
        }

        $weightedSum = 0;
        foreach ($kpiAssignments as $assignment) {
            $achievement = $assignment->achievement_percentage ?? 0;
            $weightedSum += $achievement * ($assignment->weight / $totalWeight);
        }

        // Get manager's KPI rating if available
        $managerReviewer = $participant->evaluationReviewers()
            ->byType('manager')
            ->submitted()
            ->first();

        $managerKpiRating = null;
        if ($managerReviewer && $managerReviewer->evaluationResponse) {
            $managerKpiRating = $managerReviewer->evaluationResponse->getAverageKpiRating();
        }

        $this->update([
            'kpi_achievement_score' => round($weightedSum, 2),
            'manager_kpi_rating' => $managerKpiRating,
        ]);

        return $this->fresh();
    }

    /**
     * Apply calibration adjustments to final scores.
     *
     * @param  array<string, mixed>  $calibrationData
     */
    public function calibrate(array $calibrationData, int $calibratorId): self
    {
        $this->update([
            'final_competency_score' => $calibrationData['final_competency_score'] ?? $this->overall_competency_avg,
            'final_kpi_score' => $calibrationData['final_kpi_score'] ?? $this->kpi_achievement_score,
            'final_overall_score' => $calibrationData['final_overall_score'] ?? null,
            'final_rating' => $calibrationData['final_rating'] ?? null,
            'calibration_notes' => $calibrationData['calibration_notes'] ?? null,
            'calibrated_at' => now(),
            'calibrated_by' => $calibratorId,
        ]);

        return $this->fresh();
    }

    /**
     * Record employee acknowledgement.
     */
    public function acknowledge(?string $comments = null): self
    {
        $this->update([
            'employee_acknowledged_at' => now(),
            'employee_comments' => $comments,
        ]);

        return $this->fresh();
    }

    /**
     * Check if the summary has been calibrated.
     */
    public function isCalibrated(): bool
    {
        return $this->calibrated_at !== null;
    }

    /**
     * Check if the employee has acknowledged the results.
     */
    public function isAcknowledged(): bool
    {
        return $this->employee_acknowledged_at !== null;
    }

    /**
     * Get the final rating label for display.
     */
    public function getFinalRatingLabel(): ?string
    {
        return match ($this->final_rating) {
            'exceptional' => 'Exceptional',
            'exceeds_expectations' => 'Exceeds Expectations',
            'meets_expectations' => 'Meets Expectations',
            'needs_improvement' => 'Needs Improvement',
            'unsatisfactory' => 'Unsatisfactory',
            default => $this->final_rating,
        };
    }
}
