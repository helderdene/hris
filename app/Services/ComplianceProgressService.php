<?php

namespace App\Services;

use App\Enums\ComplianceAssignmentStatus;
use App\Enums\ComplianceProgressStatus;
use App\Events\ComplianceAssignmentCompleted;
use App\Events\ComplianceModuleCompleted;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceModule;
use App\Models\ComplianceProgress;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing compliance training progress.
 *
 * Handles module progress tracking, completion checks,
 * and assignment lifecycle updates based on progress.
 */
class ComplianceProgressService
{
    public function __construct(
        protected ComplianceCertificateService $certificateService
    ) {}

    /**
     * Start a module for an assignment.
     */
    public function startModule(ComplianceAssignment $assignment, ComplianceModule $module): ComplianceProgress
    {
        $progress = $this->getOrCreateProgress($assignment, $module);

        // Start the assignment if this is the first module started
        if ($assignment->status === ComplianceAssignmentStatus::Pending) {
            $assignment->start();
        }

        // Start the module progress
        if ($progress->status === ComplianceProgressStatus::NotStarted) {
            $progress->start();
        } else {
            $progress->update(['last_accessed_at' => now()]);
        }

        return $progress->fresh();
    }

    /**
     * Update progress for a module.
     */
    public function updateProgress(
        ComplianceProgress $progress,
        float $percentage,
        ?array $positionData = null,
        int $timeSpentMinutes = 0
    ): ComplianceProgress {
        $progress->updateProgress($percentage, $positionData);

        if ($timeSpentMinutes > 0) {
            $progress->addTimeSpent($timeSpentMinutes);
        }

        // Update assignment total time
        $this->updateAssignmentTotalTime($progress->complianceAssignment);

        return $progress->fresh();
    }

    /**
     * Complete a module.
     */
    public function completeModule(
        ComplianceProgress $progress,
        ?float $score = null
    ): ComplianceProgress {
        return DB::transaction(function () use ($progress, $score) {
            $progress->complete($score);

            // Dispatch module completed event
            event(new ComplianceModuleCompleted($progress));

            // Check if all required modules are complete
            $this->checkAssignmentCompletion($progress->complianceAssignment);

            return $progress->fresh();
        });
    }

    /**
     * Check if all required modules are completed and complete the assignment.
     */
    public function checkAssignmentCompletion(ComplianceAssignment $assignment): bool
    {
        $assignment->refresh();

        // Get all required modules
        $requiredModules = $assignment->complianceCourse->modules()
            ->where('is_required', true)
            ->pluck('id');

        // Get completed progress for this assignment
        $completedModules = $assignment->progress()
            ->where('status', ComplianceProgressStatus::Completed)
            ->pluck('compliance_module_id');

        // Check if all required modules are completed
        $allComplete = $requiredModules->every(function ($moduleId) use ($completedModules) {
            return $completedModules->contains($moduleId);
        });

        if ($allComplete && $assignment->status !== ComplianceAssignmentStatus::Completed) {
            return $this->completeAssignment($assignment);
        }

        return false;
    }

    /**
     * Complete an assignment.
     */
    protected function completeAssignment(ComplianceAssignment $assignment): bool
    {
        return DB::transaction(function () use ($assignment) {
            // Calculate final score (average of all assessment scores)
            $finalScore = $this->calculateFinalScore($assignment);

            // Complete the assignment
            $assignment->complete($finalScore);

            // Issue certificate
            $this->certificateService->issueCertificate($assignment);

            // Dispatch completed event
            event(new ComplianceAssignmentCompleted($assignment));

            return true;
        });
    }

    /**
     * Calculate the final score for an assignment.
     */
    protected function calculateFinalScore(ComplianceAssignment $assignment): ?float
    {
        $assessmentProgress = $assignment->progress()
            ->whereHas('complianceModule', function ($query) {
                $query->where('content_type', 'assessment');
            })
            ->whereNotNull('best_score')
            ->get();

        if ($assessmentProgress->isEmpty()) {
            return null;
        }

        return round($assessmentProgress->avg('best_score'), 2);
    }

    /**
     * Update the total time spent on an assignment.
     */
    protected function updateAssignmentTotalTime(ComplianceAssignment $assignment): void
    {
        $totalMinutes = $assignment->progress()->sum('time_spent_minutes');
        $assignment->update(['total_time_minutes' => $totalMinutes]);
    }

    /**
     * Get or create progress record for a module.
     */
    public function getOrCreateProgress(
        ComplianceAssignment $assignment,
        ComplianceModule $module
    ): ComplianceProgress {
        return ComplianceProgress::firstOrCreate([
            'compliance_assignment_id' => $assignment->id,
            'compliance_module_id' => $module->id,
        ], [
            'status' => ComplianceProgressStatus::NotStarted,
        ]);
    }

    /**
     * Get the next incomplete module for an assignment.
     */
    public function getNextModule(ComplianceAssignment $assignment): ?ComplianceModule
    {
        $completedModuleIds = $assignment->progress()
            ->where('status', ComplianceProgressStatus::Completed)
            ->pluck('compliance_module_id');

        return $assignment->complianceCourse->modules()
            ->whereNotIn('id', $completedModuleIds)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Get the current module in progress.
     */
    public function getCurrentModule(ComplianceAssignment $assignment): ?ComplianceModule
    {
        $inProgressProgress = $assignment->progress()
            ->where('status', ComplianceProgressStatus::InProgress)
            ->first();

        return $inProgressProgress?->complianceModule;
    }

    /**
     * Reset progress for an assignment (for retakes).
     */
    public function resetProgress(ComplianceAssignment $assignment): void
    {
        $assignment->progress()->update([
            'status' => ComplianceProgressStatus::NotStarted,
            'started_at' => null,
            'completed_at' => null,
            'time_spent_minutes' => 0,
            'progress_percentage' => 0,
            'position_data' => null,
            'best_score' => null,
            'attempts_made' => 0,
            'last_accessed_at' => null,
        ]);

        $assignment->update([
            'status' => ComplianceAssignmentStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
            'final_score' => null,
            'attempts_used' => $assignment->attempts_used + 1,
            'total_time_minutes' => 0,
        ]);
    }
}
