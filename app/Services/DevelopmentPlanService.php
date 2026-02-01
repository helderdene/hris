<?php

namespace App\Services;

use App\Enums\DevelopmentItemStatus;
use App\Enums\DevelopmentPlanStatus;
use App\Enums\GoalPriority;
use App\Models\DevelopmentActivity;
use App\Models\DevelopmentPlan;
use App\Models\DevelopmentPlanCheckIn;
use App\Models\DevelopmentPlanItem;
use App\Models\Employee;
use App\Models\PerformanceCycleParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service class for managing development plans.
 *
 * Handles business logic for creating, updating, and managing
 * development plans, items, activities, and check-ins.
 */
class DevelopmentPlanService
{
    /**
     * Create a new development plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPlan(
        Employee $employee,
        array $data,
        User $createdBy,
        ?PerformanceCycleParticipant $participant = null
    ): DevelopmentPlan {
        return DevelopmentPlan::create([
            'employee_id' => $employee->id,
            'performance_cycle_participant_id' => $participant?->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => DevelopmentPlanStatus::Draft,
            'start_date' => $data['start_date'] ?? null,
            'target_completion_date' => $data['target_completion_date'] ?? null,
            'career_path_notes' => $data['career_path_notes'] ?? null,
            'manager_id' => $data['manager_id'] ?? $employee->supervisor_id,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * Create a development plan from evaluation competency gaps.
     *
     * @return array{plan: DevelopmentPlan, items: array<DevelopmentPlanItem>}
     */
    public function createFromEvaluationGaps(
        PerformanceCycleParticipant $participant,
        User $createdBy,
        ?string $title = null
    ): array {
        return DB::transaction(function () use ($participant, $createdBy, $title) {
            $employee = $participant->employee;

            // Create the plan
            $plan = $this->createPlan(
                $employee,
                [
                    'title' => $title ?? 'Development Plan - '.$participant->performanceCycleInstance->name,
                    'description' => 'Development plan created from evaluation results',
                ],
                $createdBy,
                $participant
            );

            // Get competency gaps from evaluations
            $gaps = $this->getCompetencyGaps($participant);
            $items = [];

            foreach ($gaps as $gap) {
                $item = $this->addItem($plan, [
                    'competency_id' => $gap['competency_id'],
                    'title' => 'Develop '.$gap['competency_name'],
                    'description' => 'Improve proficiency from level '.$gap['current_level'].' to level '.$gap['target_level'],
                    'current_level' => $gap['current_level'],
                    'target_level' => $gap['target_level'],
                    'priority' => $this->determinePriority($gap['gap_size']),
                ]);
                $items[] = $item;
            }

            return ['plan' => $plan, 'items' => $items];
        });
    }

    /**
     * Get competency gaps from evaluation results.
     *
     * @return array<array{competency_id: int, competency_name: string, current_level: int, target_level: int, gap_size: int}>
     */
    public function getCompetencyGaps(PerformanceCycleParticipant $participant): array
    {
        $gaps = [];

        $evaluations = $participant->competencyEvaluations()
            ->with('competency')
            ->get();

        foreach ($evaluations as $evaluation) {
            $competency = $evaluation->competency;

            if ($competency === null) {
                continue;
            }

            // Get the final rating (manager's rating or average)
            $currentLevel = $evaluation->manager_rating ?? $evaluation->self_rating ?? null;

            if ($currentLevel === null) {
                continue;
            }

            // Get the required proficiency for this competency
            // This might come from position requirements or a default target
            $targetLevel = $this->getTargetProficiency($competency, $participant->employee);

            if ($targetLevel === null || $currentLevel >= $targetLevel) {
                continue;
            }

            $gaps[] = [
                'competency_id' => $competency->id,
                'competency_name' => $competency->name,
                'current_level' => (int) $currentLevel,
                'target_level' => $targetLevel,
                'gap_size' => $targetLevel - (int) $currentLevel,
            ];
        }

        // Sort by gap size (largest gaps first)
        usort($gaps, fn ($a, $b) => $b['gap_size'] <=> $a['gap_size']);

        return $gaps;
    }

    /**
     * Get the target proficiency level for a competency.
     */
    protected function getTargetProficiency($competency, Employee $employee): ?int
    {
        // Try to get from position competency matrix first
        if ($employee->position_id !== null) {
            $positionCompetency = $competency->positions()
                ->where('position_id', $employee->position_id)
                ->first();

            if ($positionCompetency !== null) {
                return $positionCompetency->pivot->required_proficiency ?? null;
            }
        }

        // Default target: current level + 1, max 5
        return 5;
    }

    /**
     * Determine priority based on gap size.
     */
    protected function determinePriority(int $gapSize): string
    {
        if ($gapSize >= 3) {
            return GoalPriority::High->value;
        }

        if ($gapSize >= 2) {
            return GoalPriority::Medium->value;
        }

        return GoalPriority::Low->value;
    }

    /**
     * Add an item to a development plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function addItem(DevelopmentPlan $plan, array $data): DevelopmentPlanItem
    {
        return DevelopmentPlanItem::create([
            'development_plan_id' => $plan->id,
            'competency_id' => $data['competency_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'current_level' => $data['current_level'] ?? null,
            'target_level' => $data['target_level'] ?? null,
            'priority' => $data['priority'] ?? GoalPriority::Medium->value,
            'status' => DevelopmentItemStatus::NotStarted,
            'progress_percentage' => 0,
        ]);
    }

    /**
     * Update a development plan item.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(DevelopmentPlanItem $item, array $data): DevelopmentPlanItem
    {
        $item->update($data);

        return $item->fresh();
    }

    /**
     * Add an activity to a development plan item.
     *
     * @param  array<string, mixed>  $data
     */
    public function addActivity(DevelopmentPlanItem $item, array $data): DevelopmentActivity
    {
        return DevelopmentActivity::create([
            'development_plan_item_id' => $item->id,
            'activity_type' => $data['activity_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'resource_url' => $data['resource_url'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'is_completed' => false,
        ]);
    }

    /**
     * Update an activity.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateActivity(DevelopmentActivity $activity, array $data): DevelopmentActivity
    {
        $activity->update($data);

        return $activity->fresh();
    }

    /**
     * Complete an activity.
     */
    public function completeActivity(DevelopmentActivity $activity, ?string $notes = null): DevelopmentActivity
    {
        $activity->markCompleted($notes);

        // Update plan progress
        $plan = $activity->developmentPlanItem->developmentPlan;
        $this->updatePlanProgress($plan);

        return $activity->fresh();
    }

    /**
     * Add a check-in to a development plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function addCheckIn(DevelopmentPlan $plan, array $data, User $createdBy): DevelopmentPlanCheckIn
    {
        return DevelopmentPlanCheckIn::create([
            'development_plan_id' => $plan->id,
            'check_in_date' => $data['check_in_date'],
            'notes' => $data['notes'],
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * Update item progress based on activities.
     */
    public function updateItemProgress(DevelopmentPlanItem $item): void
    {
        $item->updateProgress();
    }

    /**
     * Update plan progress based on items.
     */
    public function updatePlanProgress(DevelopmentPlan $plan): void
    {
        $progress = $plan->calculateProgress();

        // Auto-start plan if not started and has progress
        if ($plan->status === DevelopmentPlanStatus::Approved && $progress > 0) {
            $plan->start();
        }

        // Auto-complete plan if all items are complete
        $items = $plan->items()->get();
        if ($items->isNotEmpty()) {
            $allCompleted = $items->every(fn ($item) => $item->status === DevelopmentItemStatus::Completed);
            if ($allCompleted && $plan->status === DevelopmentPlanStatus::InProgress) {
                $plan->complete();
            }
        }
    }

    /**
     * Get statistics for an employee's development plans.
     *
     * @return array{total: int, active: int, completed: int, pending_approval: int, overall_progress: float}
     */
    public function getStatistics(Employee $employee): array
    {
        $plans = $employee->developmentPlans()->get();

        $activePlans = $plans->filter(fn ($p) => in_array($p->status, [
            DevelopmentPlanStatus::Approved,
            DevelopmentPlanStatus::InProgress,
        ]));

        $totalProgress = $activePlans->sum(fn ($p) => $p->calculateProgress());
        $overallProgress = $activePlans->count() > 0
            ? $totalProgress / $activePlans->count()
            : 0;

        return [
            'total' => $plans->count(),
            'active' => $activePlans->count(),
            'completed' => $plans->where('status', DevelopmentPlanStatus::Completed)->count(),
            'pending_approval' => $plans->where('status', DevelopmentPlanStatus::PendingApproval)->count(),
            'overall_progress' => round($overallProgress, 2),
        ];
    }
}
