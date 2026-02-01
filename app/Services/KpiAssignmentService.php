<?php

namespace App\Services;

use App\Enums\KpiAssignmentStatus;
use App\Models\KpiAssignment;
use App\Models\KpiProgressEntry;
use App\Models\KpiTemplate;
use App\Models\PerformanceCycleParticipant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Service for managing KPI assignments and progress tracking.
 *
 * Provides methods for assigning KPIs to participants, recording progress,
 * calculating achievements, and generating summaries.
 */
class KpiAssignmentService
{
    /**
     * Assign a KPI template to a participant.
     */
    public function assignKpiToParticipant(
        KpiTemplate $template,
        PerformanceCycleParticipant $participant,
        float $targetValue,
        float $weight = 1.0,
        ?string $notes = null
    ): KpiAssignment {
        return KpiAssignment::create([
            'kpi_template_id' => $template->id,
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => $targetValue,
            'weight' => $weight,
            'notes' => $notes,
            'status' => KpiAssignmentStatus::Pending,
        ]);
    }

    /**
     * Bulk assign a KPI template to multiple participants.
     *
     * @param  array<int>  $participantIds
     * @return Collection<int, KpiAssignment>
     */
    public function bulkAssignKpi(
        KpiTemplate $template,
        array $participantIds,
        float $targetValue,
        float $weight = 1.0
    ): Collection {
        $assignments = collect();

        foreach ($participantIds as $participantId) {
            // Check if assignment already exists
            $existing = KpiAssignment::where('kpi_template_id', $template->id)
                ->where('performance_cycle_participant_id', $participantId)
                ->first();

            if ($existing) {
                continue;
            }

            $assignment = KpiAssignment::create([
                'kpi_template_id' => $template->id,
                'performance_cycle_participant_id' => $participantId,
                'target_value' => $targetValue,
                'weight' => $weight,
                'status' => KpiAssignmentStatus::Pending,
            ]);

            $assignments->push($assignment);
        }

        return $assignments;
    }

    /**
     * Record progress for a KPI assignment.
     */
    public function recordProgress(
        KpiAssignment $assignment,
        float $value,
        ?string $notes = null,
        ?User $user = null
    ): KpiProgressEntry {
        $entry = KpiProgressEntry::create([
            'kpi_assignment_id' => $assignment->id,
            'value' => $value,
            'notes' => $notes,
            'recorded_at' => now(),
            'recorded_by' => $user?->id ?? auth()->id(),
        ]);

        // Update the assignment's actual value
        $assignment->update([
            'actual_value' => $value,
            'status' => KpiAssignmentStatus::InProgress,
        ]);

        // Recalculate achievement
        $this->calculateAchievement($assignment);

        return $entry;
    }

    /**
     * Calculate achievement percentage for an assignment.
     */
    public function calculateAchievement(KpiAssignment $assignment): float
    {
        $assignment->refresh();

        if ($assignment->target_value == 0) {
            $achievement = $assignment->actual_value > 0 ? 100 : 0;
        } else {
            $achievement = ($assignment->actual_value / $assignment->target_value) * 100;
        }

        // Cap at 200% to handle over-achievement reasonably
        $achievement = min($achievement, 200);

        $assignment->update(['achievement_percentage' => $achievement]);

        return $achievement;
    }

    /**
     * Get KPI summary for a participant.
     *
     * Returns weighted average achievement and breakdown by KPI.
     *
     * @return array{
     *     total_kpis: int,
     *     completed_kpis: int,
     *     pending_kpis: int,
     *     in_progress_kpis: int,
     *     weighted_average_achievement: float,
     *     total_weight: float,
     *     kpis: Collection<int, KpiAssignment>
     * }
     */
    public function getParticipantKpiSummary(PerformanceCycleParticipant $participant): array
    {
        $kpis = KpiAssignment::forParticipant($participant->id)
            ->with('kpiTemplate')
            ->get();

        $totalKpis = $kpis->count();
        $completedKpis = $kpis->where('status', KpiAssignmentStatus::Completed)->count();
        $pendingKpis = $kpis->where('status', KpiAssignmentStatus::Pending)->count();
        $inProgressKpis = $kpis->where('status', KpiAssignmentStatus::InProgress)->count();

        // Calculate weighted average achievement
        $totalWeight = $kpis->sum('weight');
        $weightedSum = $kpis->sum(function ($kpi) {
            return ($kpi->achievement_percentage ?? 0) * $kpi->weight;
        });

        $weightedAverageAchievement = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;

        return [
            'total_kpis' => $totalKpis,
            'completed_kpis' => $completedKpis,
            'pending_kpis' => $pendingKpis,
            'in_progress_kpis' => $inProgressKpis,
            'weighted_average_achievement' => round($weightedAverageAchievement, 2),
            'total_weight' => $totalWeight,
            'kpis' => $kpis,
        ];
    }

    /**
     * Mark an assignment as completed.
     */
    public function markCompleted(KpiAssignment $assignment): void
    {
        $this->calculateAchievement($assignment);

        $assignment->update([
            'status' => KpiAssignmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get progress history for an assignment.
     *
     * @return Collection<int, KpiProgressEntry>
     */
    public function getProgressHistory(KpiAssignment $assignment): Collection
    {
        return $assignment->progressEntries()
            ->with('recordedByUser')
            ->orderBy('recorded_at', 'desc')
            ->get();
    }
}
