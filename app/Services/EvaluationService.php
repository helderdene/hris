<?php

namespace App\Services;

use App\Enums\AssignmentMethod;
use App\Enums\EvaluationReviewerStatus;
use App\Enums\EvaluationStatus;
use App\Enums\ReviewerType;
use App\Models\Employee;
use App\Models\EvaluationCompetencyRating;
use App\Models\EvaluationKpiRating;
use App\Models\EvaluationResponse;
use App\Models\EvaluationReviewer;
use App\Models\EvaluationSummary;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\PositionCompetency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing 360-degree performance evaluations.
 *
 * Handles reviewer assignment, response saving, score calculation,
 * and calibration workflows.
 */
class EvaluationService
{
    /**
     * Assign all reviewers for a participant (self, manager, peers, direct reports).
     *
     * @return Collection<int, EvaluationReviewer>
     */
    public function assignReviewers(PerformanceCycleParticipant $participant): Collection
    {
        $reviewers = collect();
        $instance = $participant->performanceCycleInstance;

        // Always assign self reviewer
        $selfReviewer = $this->assignSelfReviewer($participant);
        if ($selfReviewer) {
            $reviewers->push($selfReviewer);
        }

        // Assign manager reviewer if exists
        $managerReviewer = $this->assignManagerReviewer($participant);
        if ($managerReviewer) {
            $reviewers->push($managerReviewer);
        }

        // Assign peer reviewers if enabled
        if ($instance->enable_peer_review) {
            $peerReviewers = $this->assignPeerReviewers($participant);
            $reviewers = $reviewers->merge($peerReviewers);
        }

        // Assign direct report reviewers if enabled
        if ($instance->enable_direct_report_review) {
            $drReviewers = $this->assignDirectReportReviewers($participant);
            $reviewers = $reviewers->merge($drReviewers);
        }

        // Update participant status
        $this->updateParticipantEvaluationStatus($participant);

        return $reviewers;
    }

    /**
     * Assign self reviewer for a participant.
     */
    public function assignSelfReviewer(PerformanceCycleParticipant $participant): ?EvaluationReviewer
    {
        // Check if already assigned
        $existing = $participant->evaluationReviewers()
            ->byType(ReviewerType::Self)
            ->first();

        if ($existing) {
            return $existing;
        }

        return EvaluationReviewer::create([
            'performance_cycle_participant_id' => $participant->id,
            'reviewer_employee_id' => $participant->employee_id,
            'reviewer_type' => ReviewerType::Self,
            'status' => EvaluationReviewerStatus::Pending,
            'assignment_method' => AssignmentMethod::Automatic,
            'invited_at' => now(),
        ]);
    }

    /**
     * Assign manager reviewer for a participant.
     */
    public function assignManagerReviewer(PerformanceCycleParticipant $participant): ?EvaluationReviewer
    {
        if (! $participant->manager_id) {
            return null;
        }

        // Check if already assigned
        $existing = $participant->evaluationReviewers()
            ->byType(ReviewerType::Manager)
            ->first();

        if ($existing) {
            return $existing;
        }

        return EvaluationReviewer::create([
            'performance_cycle_participant_id' => $participant->id,
            'reviewer_employee_id' => $participant->manager_id,
            'reviewer_type' => ReviewerType::Manager,
            'status' => EvaluationReviewerStatus::Pending,
            'assignment_method' => AssignmentMethod::Automatic,
            'invited_at' => now(),
        ]);
    }

    /**
     * Auto-assign peer reviewers from the same department.
     *
     * @return Collection<int, EvaluationReviewer>
     */
    public function assignPeerReviewers(PerformanceCycleParticipant $participant): Collection
    {
        $employee = $participant->employee;
        $instance = $participant->performanceCycleInstance;

        // Get existing peer count
        $existingPeers = $participant->evaluationReviewers()
            ->byType(ReviewerType::Peer)
            ->count();

        $minPeers = $participant->min_peer_reviewers ?? 3;
        $maxPeers = $participant->max_peer_reviewers ?? 5;
        $targetCount = min($maxPeers, max($minPeers, $maxPeers)) - $existingPeers;

        if ($targetCount <= 0) {
            return collect();
        }

        // Get existing reviewer employee IDs to exclude
        $excludeIds = $participant->evaluationReviewers()
            ->pluck('reviewer_employee_id')
            ->push($participant->employee_id) // Exclude self
            ->push($participant->manager_id) // Exclude manager
            ->filter()
            ->unique()
            ->toArray();

        // Find eligible peers from the same department
        $eligiblePeers = Employee::query()
            ->active()
            ->where('department_id', $employee->department_id)
            ->whereNotIn('id', $excludeIds)
            ->inRandomOrder()
            ->limit($targetCount)
            ->get();

        $reviewers = collect();

        foreach ($eligiblePeers as $peer) {
            $reviewer = EvaluationReviewer::create([
                'performance_cycle_participant_id' => $participant->id,
                'reviewer_employee_id' => $peer->id,
                'reviewer_type' => ReviewerType::Peer,
                'status' => EvaluationReviewerStatus::Pending,
                'assignment_method' => AssignmentMethod::Automatic,
                'invited_at' => now(),
            ]);
            $reviewers->push($reviewer);
        }

        return $reviewers;
    }

    /**
     * Assign direct reports as reviewers for a participant.
     *
     * @return Collection<int, EvaluationReviewer>
     */
    public function assignDirectReportReviewers(PerformanceCycleParticipant $participant): Collection
    {
        $employee = $participant->employee;

        // Get existing direct report reviewer IDs
        $existingIds = $participant->evaluationReviewers()
            ->byType(ReviewerType::DirectReport)
            ->pluck('reviewer_employee_id')
            ->toArray();

        // Find direct reports
        $directReports = Employee::query()
            ->active()
            ->where('supervisor_id', $employee->id)
            ->whereNotIn('id', $existingIds)
            ->get();

        $reviewers = collect();

        foreach ($directReports as $directReport) {
            $reviewer = EvaluationReviewer::create([
                'performance_cycle_participant_id' => $participant->id,
                'reviewer_employee_id' => $directReport->id,
                'reviewer_type' => ReviewerType::DirectReport,
                'status' => EvaluationReviewerStatus::Pending,
                'assignment_method' => AssignmentMethod::Automatic,
                'invited_at' => now(),
            ]);
            $reviewers->push($reviewer);
        }

        return $reviewers;
    }

    /**
     * Manually assign a reviewer (by HR or manager).
     */
    public function assignReviewer(
        PerformanceCycleParticipant $participant,
        int $reviewerEmployeeId,
        ReviewerType $type,
        AssignmentMethod $method,
        ?int $assignedBy = null
    ): EvaluationReviewer {
        // Check for duplicate
        $existing = $participant->evaluationReviewers()
            ->where('reviewer_employee_id', $reviewerEmployeeId)
            ->where('reviewer_type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        return EvaluationReviewer::create([
            'performance_cycle_participant_id' => $participant->id,
            'reviewer_employee_id' => $reviewerEmployeeId,
            'reviewer_type' => $type,
            'status' => EvaluationReviewerStatus::Pending,
            'assignment_method' => $method,
            'assigned_by' => $assignedBy,
            'invited_at' => now(),
        ]);
    }

    /**
     * Save evaluation response as draft.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveEvaluationDraft(EvaluationReviewer $reviewer, array $data): EvaluationResponse
    {
        return DB::transaction(function () use ($reviewer, $data) {
            // Mark reviewer as in progress if pending
            if ($reviewer->status === EvaluationReviewerStatus::Pending) {
                $reviewer->start();
            }

            // Get or create response
            $response = $reviewer->getOrCreateResponse();

            // Update narrative fields
            $narrativeFields = [
                'strengths',
                'areas_for_improvement',
                'overall_comments',
                'development_suggestions',
            ];

            $narrativeData = array_intersect_key($data, array_flip($narrativeFields));
            $response->saveDraft($narrativeData);

            // Save competency ratings
            if (isset($data['competency_ratings']) && is_array($data['competency_ratings'])) {
                $this->saveCompetencyRatings($response, $data['competency_ratings']);
            }

            // Save KPI ratings (only for self/manager)
            if ($reviewer->canViewKpis() && isset($data['kpi_ratings']) && is_array($data['kpi_ratings'])) {
                $this->saveKpiRatings($response, $data['kpi_ratings']);
            }

            // Update participant status
            $this->updateParticipantEvaluationStatus($reviewer->participant);

            return $response->fresh(['competencyRatings', 'kpiRatings']);
        });
    }

    /**
     * Submit evaluation response.
     */
    public function submitEvaluation(EvaluationReviewer $reviewer): EvaluationResponse
    {
        return DB::transaction(function () use ($reviewer) {
            $response = $reviewer->evaluationResponse;

            if (! $response) {
                throw new \InvalidArgumentException('No evaluation response found to submit.');
            }

            // Mark response as submitted
            $response->submit();

            // Mark reviewer as submitted
            $reviewer->submit();

            // Update participant status
            $this->updateParticipantEvaluationStatus($reviewer->participant);

            return $response->fresh();
        });
    }

    /**
     * Decline to review.
     */
    public function declineReview(EvaluationReviewer $reviewer, ?string $reason = null): EvaluationReviewer
    {
        $reviewer->decline($reason);

        // Update participant status
        $this->updateParticipantEvaluationStatus($reviewer->participant);

        return $reviewer->fresh();
    }

    /**
     * Save competency ratings for a response.
     *
     * @param  array<int, array{rating?: int|null, comments?: string|null}>  $ratings
     */
    protected function saveCompetencyRatings(EvaluationResponse $response, array $ratings): void
    {
        foreach ($ratings as $positionCompetencyId => $ratingData) {
            EvaluationCompetencyRating::updateOrCreate(
                [
                    'evaluation_response_id' => $response->id,
                    'position_competency_id' => $positionCompetencyId,
                ],
                [
                    'rating' => $ratingData['rating'] ?? null,
                    'comments' => $ratingData['comments'] ?? null,
                ]
            );
        }
    }

    /**
     * Save KPI ratings for a response.
     *
     * @param  array<int, array{rating?: int|null, comments?: string|null}>  $ratings
     */
    protected function saveKpiRatings(EvaluationResponse $response, array $ratings): void
    {
        foreach ($ratings as $kpiAssignmentId => $ratingData) {
            EvaluationKpiRating::updateOrCreate(
                [
                    'evaluation_response_id' => $response->id,
                    'kpi_assignment_id' => $kpiAssignmentId,
                ],
                [
                    'rating' => $ratingData['rating'] ?? null,
                    'comments' => $ratingData['comments'] ?? null,
                ]
            );
        }
    }

    /**
     * Calculate competency averages for a participant.
     *
     * @return array<string, float|null>
     */
    public function calculateCompetencyAverages(PerformanceCycleParticipant $participant): array
    {
        $reviewers = $participant->evaluationReviewers()->submitted()->get();

        $averages = [
            'self' => null,
            'manager' => null,
            'peer' => null,
            'direct_report' => null,
            'overall' => null,
        ];

        $peerRatings = [];
        $drRatings = [];
        $allRatings = [];

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

            switch ($type) {
                case 'self':
                    $averages['self'] = $avg;
                    $allRatings[] = $avg;
                    break;
                case 'manager':
                    $averages['manager'] = $avg;
                    $allRatings[] = $avg;
                    break;
                case 'peer':
                    $peerRatings[] = $avg;
                    break;
                case 'direct_report':
                    $drRatings[] = $avg;
                    break;
            }
        }

        // Calculate peer average
        if (count($peerRatings) > 0) {
            $averages['peer'] = round(array_sum($peerRatings) / count($peerRatings), 2);
            $allRatings[] = $averages['peer'];
        }

        // Calculate direct report average
        if (count($drRatings) > 0) {
            $averages['direct_report'] = round(array_sum($drRatings) / count($drRatings), 2);
            $allRatings[] = $averages['direct_report'];
        }

        // Calculate overall average
        if (count($allRatings) > 0) {
            $averages['overall'] = round(array_sum($allRatings) / count($allRatings), 2);
        }

        return $averages;
    }

    /**
     * Generate or update evaluation summary for a participant.
     */
    public function generateSummary(PerformanceCycleParticipant $participant): EvaluationSummary
    {
        $summary = $participant->getOrCreateEvaluationSummary();

        // Calculate and update averages
        $summary->calculateAverages();
        $summary->calculateKpiScore();

        return $summary->fresh();
    }

    /**
     * Calibrate final scores for a participant.
     *
     * @param  array<string, mixed>  $calibrationData
     */
    public function calibrate(
        PerformanceCycleParticipant $participant,
        array $calibrationData,
        int $calibratorId
    ): EvaluationSummary {
        $summary = $participant->getOrCreateEvaluationSummary();
        $summary->calibrate($calibrationData, $calibratorId);

        // Update participant status to completed
        $participant->update([
            'evaluation_status' => EvaluationStatus::Completed,
        ]);

        return $summary->fresh();
    }

    /**
     * Update participant evaluation status based on reviewer states.
     */
    public function updateParticipantEvaluationStatus(PerformanceCycleParticipant $participant): void
    {
        $reviewers = $participant->evaluationReviewers;

        if ($reviewers->isEmpty()) {
            $participant->update(['evaluation_status' => EvaluationStatus::NotStarted]);

            return;
        }

        $selfReviewer = $reviewers->firstWhere('reviewer_type', ReviewerType::Self);
        $otherReviewers = $reviewers->where('reviewer_type', '!=', ReviewerType::Self);

        // Check self evaluation status
        $selfSubmitted = $selfReviewer && $selfReviewer->status === EvaluationReviewerStatus::Submitted;
        $selfInProgress = $selfReviewer && $selfReviewer->status === EvaluationReviewerStatus::InProgress;

        // Check other reviewers
        $allOthersSubmittedOrDeclined = $otherReviewers->every(function ($r) {
            return in_array($r->status, [EvaluationReviewerStatus::Submitted, EvaluationReviewerStatus::Declined]);
        });

        $anyOtherInProgress = $otherReviewers->contains(function ($r) {
            return $r->status === EvaluationReviewerStatus::InProgress;
        });

        // Determine status
        if (! $selfSubmitted && $selfInProgress) {
            $newStatus = EvaluationStatus::SelfInProgress;
        } elseif ($selfSubmitted && ! $allOthersSubmittedOrDeclined) {
            if ($anyOtherInProgress) {
                $newStatus = EvaluationStatus::Reviewing;
            } else {
                $newStatus = EvaluationStatus::AwaitingReviewers;
            }
        } elseif ($selfSubmitted && $allOthersSubmittedOrDeclined) {
            // All reviews complete, ready for calibration
            $newStatus = EvaluationStatus::Calibration;

            // Auto-generate summary if not exists
            $this->generateSummary($participant);
        } else {
            $newStatus = EvaluationStatus::NotStarted;
        }

        // Only update if different to avoid unnecessary writes
        if ($participant->evaluation_status !== $newStatus) {
            $participant->update(['evaluation_status' => $newStatus]);
        }
    }

    /**
     * Get competencies for a participant's position.
     *
     * @return Collection<int, PositionCompetency>
     */
    public function getParticipantCompetencies(PerformanceCycleParticipant $participant): Collection
    {
        $employee = $participant->employee;

        if (! $employee || ! $employee->position_id) {
            return collect();
        }

        return PositionCompetency::forPosition($employee->position_id)
            ->withActiveCompetency()
            ->with('competency')
            ->get();
    }

    /**
     * Initialize evaluations for all participants in an instance.
     */
    public function initializeInstanceEvaluations(PerformanceCycleInstance $instance): void
    {
        $participants = $instance->participants()->included()->get();

        foreach ($participants as $participant) {
            $this->assignReviewers($participant);
        }
    }
}
