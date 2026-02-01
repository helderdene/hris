<?php

namespace App\Services;

use App\Enums\DevelopmentPlanStatus;
use App\Enums\EvaluationStatus;
use App\Enums\GoalStatus;
use App\Enums\KpiAssignmentStatus;
use App\Models\DevelopmentPlan;
use App\Models\EvaluationSummary;
use App\Models\Goal;
use App\Models\KpiAssignment;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service class for Performance Analytics Dashboard calculations.
 *
 * Provides deep analytics for evaluations, development plans, goals,
 * and KPIs across performance cycles.
 */
class PerformanceAnalyticsService
{
    /**
     * Get the date range for filtering.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getDateRange(?Carbon $startDate, ?Carbon $endDate): array
    {
        $end = $endDate ?? Carbon::now();
        $start = $startDate ?? $end->copy()->subYear();

        return ['start' => $start->startOfDay(), 'end' => $end->endOfDay()];
    }

    // =========================================================================
    // SUMMARY METRICS (Fast-loading)
    // =========================================================================

    /**
     * Get summary metrics for the dashboard KPI cards.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     totalEvaluations: int,
     *     completedEvaluations: int,
     *     averageRating: float|null,
     *     activeDevelopmentPlans: int,
     *     activeGoals: int,
     *     goalsAchieved: int
     * }
     */
    public function getSummaryMetrics(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        // Get participants in date range
        $participantQuery = PerformanceCycleParticipant::query()
            ->included()
            ->whereHas('performanceCycleInstance', function ($q) use ($range) {
                $q->whereBetween('end_date', [$range['start'], $range['end']]);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $participantQuery->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $totalEvaluations = (clone $participantQuery)->count();

        // Completed evaluations (has final rating)
        $completedQuery = (clone $participantQuery)
            ->whereHas('evaluationSummary', function ($q) {
                $q->whereNotNull('final_rating');
            });
        $completedEvaluations = $completedQuery->count();

        // Average rating
        $participantIds = (clone $participantQuery)->pluck('id');
        $summaries = EvaluationSummary::query()
            ->whereIn('performance_cycle_participant_id', $participantIds)
            ->whereNotNull('final_overall_score')
            ->pluck('final_overall_score');

        $averageRating = $summaries->isNotEmpty()
            ? round($summaries->avg(), 2)
            : null;

        // Active development plans
        $devPlanQuery = DevelopmentPlan::query()->active();
        if ($departmentIds !== null && count($departmentIds) > 0) {
            $devPlanQuery->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }
        $activeDevelopmentPlans = $devPlanQuery->count();

        // Goals
        $goalQuery = Goal::query();
        if ($departmentIds !== null && count($departmentIds) > 0) {
            $goalQuery->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $activeGoals = (clone $goalQuery)->where('status', GoalStatus::Active)->count();
        $goalsAchieved = (clone $goalQuery)
            ->where('status', GoalStatus::Completed)
            ->whereBetween('completed_at', [$range['start'], $range['end']])
            ->count();

        return [
            'totalEvaluations' => $totalEvaluations,
            'completedEvaluations' => $completedEvaluations,
            'averageRating' => $averageRating,
            'activeDevelopmentPlans' => $activeDevelopmentPlans,
            'activeGoals' => $activeGoals,
            'goalsAchieved' => $goalsAchieved,
        ];
    }

    // =========================================================================
    // EVALUATION COMPLETION METRICS
    // =========================================================================

    /**
     * Get evaluation completion metrics by status and cycle.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     byStatus: array<array{status: string, label: string, count: int}>,
     *     byCycle: array<array{cycle: string, total: int, completed: int, rate: float}>,
     *     overallRate: float
     * }
     */
    public function getEvaluationCompletionMetrics(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $baseQuery = PerformanceCycleParticipant::query()
            ->included()
            ->whereHas('performanceCycleInstance', function ($q) use ($range) {
                $q->whereBetween('end_date', [$range['start'], $range['end']]);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $baseQuery->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        // By status
        $statusCounts = (clone $baseQuery)
            ->select('evaluation_status', DB::raw('COUNT(*) as count'))
            ->groupBy('evaluation_status')
            ->get()
            ->keyBy('evaluation_status');

        $statuses = [
            EvaluationStatus::NotStarted->value => EvaluationStatus::NotStarted->label(),
            EvaluationStatus::SelfInProgress->value => EvaluationStatus::SelfInProgress->label(),
            EvaluationStatus::AwaitingReviewers->value => EvaluationStatus::AwaitingReviewers->label(),
            EvaluationStatus::Reviewing->value => EvaluationStatus::Reviewing->label(),
            EvaluationStatus::Calibration->value => EvaluationStatus::Calibration->label(),
            EvaluationStatus::Completed->value => EvaluationStatus::Completed->label(),
        ];

        $byStatus = [];
        foreach ($statuses as $status => $label) {
            $byStatus[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($statusCounts->get($status)?->count ?? 0),
            ];
        }

        // By cycle
        $byCycle = PerformanceCycleInstance::query()
            ->whereBetween('end_date', [$range['start'], $range['end']])
            ->with(['participants' => function ($q) use ($departmentIds) {
                $q->included();
                if ($departmentIds !== null && count($departmentIds) > 0) {
                    $q->whereHas('employee', function ($eq) use ($departmentIds) {
                        $eq->whereIn('department_id', $departmentIds);
                    });
                }
            }])
            ->orderBy('end_date')
            ->get()
            ->map(function ($instance) {
                $total = $instance->participants->count();
                $completed = $instance->participants
                    ->where('evaluation_status', EvaluationStatus::Completed)
                    ->count();

                return [
                    'cycle' => $instance->name,
                    'total' => $total,
                    'completed' => $completed,
                    'rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                ];
            })
            ->toArray();

        // Overall rate
        $total = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)
            ->where('evaluation_status', EvaluationStatus::Completed)
            ->count();
        $overallRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        return [
            'byStatus' => $byStatus,
            'byCycle' => $byCycle,
            'overallRate' => $overallRate,
        ];
    }

    // =========================================================================
    // RATING DISTRIBUTION
    // =========================================================================

    /**
     * Get performance rating distribution.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{rating: string, count: int, label: string, percentage: float}>
     */
    public function getRatingDistribution(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $participantIds = PerformanceCycleParticipant::query()
            ->included()
            ->whereHas('performanceCycleInstance', function ($q) use ($range) {
                $q->whereBetween('end_date', [$range['start'], $range['end']]);
            })
            ->when($departmentIds !== null && count($departmentIds) > 0, function ($q) use ($departmentIds) {
                $q->whereHas('employee', function ($eq) use ($departmentIds) {
                    $eq->whereIn('department_id', $departmentIds);
                });
            })
            ->pluck('id');

        $results = EvaluationSummary::query()
            ->whereIn('performance_cycle_participant_id', $participantIds)
            ->whereNotNull('final_rating')
            ->select([
                'final_rating',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('final_rating')
            ->get()
            ->keyBy('final_rating');

        $totalRated = $results->sum('count');

        $ratings = [
            'exceptional' => 'Exceptional',
            'exceeds_expectations' => 'Exceeds Expectations',
            'meets_expectations' => 'Meets Expectations',
            'needs_improvement' => 'Needs Improvement',
            'unsatisfactory' => 'Unsatisfactory',
        ];

        return collect($ratings)->map(function ($label, $rating) use ($results, $totalRated) {
            $count = (int) ($results->get($rating)?->count ?? 0);

            return [
                'rating' => $rating,
                'count' => $count,
                'label' => $label,
                'percentage' => $totalRated > 0 ? round(($count / $totalRated) * 100, 1) : 0,
            ];
        })->values()->toArray();
    }

    // =========================================================================
    // RATING TRENDS
    // =========================================================================

    /**
     * Get rating trends over performance cycles.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     cycles: array<string>,
     *     series: array<array{rating: string, label: string, data: array<int>}>
     * }
     */
    public function getRatingTrends(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $instances = PerformanceCycleInstance::query()
            ->whereBetween('end_date', [$range['start'], $range['end']])
            ->orderBy('end_date')
            ->get();

        $ratings = [
            'exceptional' => 'Exceptional',
            'exceeds_expectations' => 'Exceeds Expectations',
            'meets_expectations' => 'Meets Expectations',
            'needs_improvement' => 'Needs Improvement',
            'unsatisfactory' => 'Unsatisfactory',
        ];

        $cycles = [];
        $seriesData = [];

        foreach ($ratings as $rating => $label) {
            $seriesData[$rating] = [
                'rating' => $rating,
                'label' => $label,
                'data' => [],
            ];
        }

        foreach ($instances as $instance) {
            $cycles[] = $instance->name;

            $participantIds = PerformanceCycleParticipant::query()
                ->where('performance_cycle_instance_id', $instance->id)
                ->included()
                ->when($departmentIds !== null && count($departmentIds) > 0, function ($q) use ($departmentIds) {
                    $q->whereHas('employee', function ($eq) use ($departmentIds) {
                        $eq->whereIn('department_id', $departmentIds);
                    });
                })
                ->pluck('id');

            $counts = EvaluationSummary::query()
                ->whereIn('performance_cycle_participant_id', $participantIds)
                ->whereNotNull('final_rating')
                ->select('final_rating', DB::raw('COUNT(*) as count'))
                ->groupBy('final_rating')
                ->get()
                ->keyBy('final_rating');

            foreach ($ratings as $rating => $label) {
                $seriesData[$rating]['data'][] = (int) ($counts->get($rating)?->count ?? 0);
            }
        }

        return [
            'cycles' => $cycles,
            'series' => array_values($seriesData),
        ];
    }

    // =========================================================================
    // DEVELOPMENT PLAN METRICS
    // =========================================================================

    /**
     * Get development plan completion metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     byStatus: array<array{status: string, label: string, count: int}>,
     *     completionRate: float,
     *     overdueCount: int,
     *     averageProgress: float,
     *     totalPlans: int
     * }
     */
    public function getDevelopmentPlanMetrics(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $baseQuery = DevelopmentPlan::query()
            ->where(function ($q) use ($range) {
                $q->whereBetween('created_at', [$range['start'], $range['end']])
                    ->orWhereBetween('target_completion_date', [$range['start'], $range['end']]);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $baseQuery->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        // By status
        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = [
            DevelopmentPlanStatus::Draft->value => 'Draft',
            DevelopmentPlanStatus::PendingApproval->value => 'Pending Approval',
            DevelopmentPlanStatus::Approved->value => 'Approved',
            DevelopmentPlanStatus::InProgress->value => 'In Progress',
            DevelopmentPlanStatus::Completed->value => 'Completed',
            DevelopmentPlanStatus::Cancelled->value => 'Cancelled',
        ];

        $byStatus = [];
        foreach ($statuses as $status => $label) {
            $byStatus[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($statusCounts->get($status)?->count ?? 0),
            ];
        }

        // Total and completion rate
        $totalPlans = (clone $baseQuery)->count();
        $completedPlans = (clone $baseQuery)
            ->where('status', DevelopmentPlanStatus::Completed)
            ->count();
        $completionRate = $totalPlans > 0 ? round(($completedPlans / $totalPlans) * 100, 1) : 0;

        // Overdue count (active plans past target date)
        $overdueCount = (clone $baseQuery)
            ->whereIn('status', [
                DevelopmentPlanStatus::Approved,
                DevelopmentPlanStatus::InProgress,
            ])
            ->whereNotNull('target_completion_date')
            ->where('target_completion_date', '<', now())
            ->count();

        // Average progress of active plans
        $activePlans = (clone $baseQuery)
            ->whereIn('status', [
                DevelopmentPlanStatus::Approved,
                DevelopmentPlanStatus::InProgress,
            ])
            ->with('items')
            ->get();

        $progressSum = 0;
        foreach ($activePlans as $plan) {
            $progressSum += $plan->calculateProgress();
        }
        $averageProgress = $activePlans->count() > 0
            ? round($progressSum / $activePlans->count(), 1)
            : 0;

        return [
            'byStatus' => $byStatus,
            'completionRate' => $completionRate,
            'overdueCount' => $overdueCount,
            'averageProgress' => $averageProgress,
            'totalPlans' => $totalPlans,
        ];
    }

    // =========================================================================
    // GOAL ACHIEVEMENT METRICS
    // =========================================================================

    /**
     * Get goal achievement metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     byStatus: array<array{status: string, label: string, count: int}>,
     *     byPriority: array<array{priority: string, count: int, achieved: int}>,
     *     achievementRate: float,
     *     averageProgress: float,
     *     totalGoals: int,
     *     overdueCount: int
     * }
     */
    public function getGoalAchievementMetrics(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $baseQuery = Goal::query()
            ->where(function ($q) use ($range) {
                $q->whereBetween('created_at', [$range['start'], $range['end']])
                    ->orWhereBetween('due_date', [$range['start'], $range['end']]);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $baseQuery->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        // By status
        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = [
            GoalStatus::Draft->value => 'Draft',
            GoalStatus::PendingApproval->value => 'Pending Approval',
            GoalStatus::Active->value => 'Active',
            GoalStatus::Completed->value => 'Completed',
            GoalStatus::Cancelled->value => 'Cancelled',
        ];

        $byStatus = [];
        foreach ($statuses as $status => $label) {
            $byStatus[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($statusCounts->get($status)?->count ?? 0),
            ];
        }

        // By priority
        $priorityCounts = (clone $baseQuery)
            ->select('priority', 'status', DB::raw('COUNT(*) as count'))
            ->groupBy('priority', 'status')
            ->get();

        $priorities = ['high', 'medium', 'low'];
        $byPriority = [];
        foreach ($priorities as $priority) {
            $priorityGoals = $priorityCounts->where('priority', $priority);
            $total = $priorityGoals->sum('count');
            $achieved = $priorityGoals->where('status', GoalStatus::Completed->value)->sum('count');

            $byPriority[] = [
                'priority' => ucfirst($priority),
                'count' => (int) $total,
                'achieved' => (int) $achieved,
            ];
        }

        // Totals
        $totalGoals = (clone $baseQuery)->count();
        $completedGoals = (clone $baseQuery)
            ->where('status', GoalStatus::Completed)
            ->count();
        $achievementRate = $totalGoals > 0 ? round(($completedGoals / $totalGoals) * 100, 1) : 0;

        // Average progress
        $progressGoals = (clone $baseQuery)
            ->whereIn('status', [GoalStatus::Active, GoalStatus::Completed])
            ->whereNotNull('progress_percentage')
            ->pluck('progress_percentage');
        $averageProgress = $progressGoals->isNotEmpty()
            ? round($progressGoals->avg(), 1)
            : 0;

        // Overdue
        $overdueCount = (clone $baseQuery)
            ->where('status', GoalStatus::Active)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        return [
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'achievementRate' => $achievementRate,
            'averageProgress' => $averageProgress,
            'totalGoals' => $totalGoals,
            'overdueCount' => $overdueCount,
        ];
    }

    // =========================================================================
    // KPI ACHIEVEMENT METRICS
    // =========================================================================

    /**
     * Get KPI achievement metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     byStatus: array<array{status: string, label: string, count: int}>,
     *     achievementDistribution: array<array{range: string, count: int}>,
     *     averageAchievement: float,
     *     totalKpis: int,
     *     overachievingCount: int
     * }
     */
    public function getKpiAchievementMetrics(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $baseQuery = KpiAssignment::query()
            ->whereHas('performanceCycleParticipant', function ($q) use ($range, $departmentIds) {
                $q->included()
                    ->whereHas('performanceCycleInstance', function ($iq) use ($range) {
                        $iq->whereBetween('end_date', [$range['start'], $range['end']]);
                    });

                if ($departmentIds !== null && count($departmentIds) > 0) {
                    $q->whereHas('employee', function ($eq) use ($departmentIds) {
                        $eq->whereIn('department_id', $departmentIds);
                    });
                }
            });

        // By status
        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = [
            KpiAssignmentStatus::Pending->value => 'Pending',
            KpiAssignmentStatus::InProgress->value => 'In Progress',
            KpiAssignmentStatus::Completed->value => 'Completed',
        ];

        $byStatus = [];
        foreach ($statuses as $status => $label) {
            $byStatus[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($statusCounts->get($status)?->count ?? 0),
            ];
        }

        // Achievement distribution (histogram)
        $achievements = (clone $baseQuery)
            ->whereNotNull('achievement_percentage')
            ->pluck('achievement_percentage');

        $ranges = [
            ['min' => 0, 'max' => 50, 'label' => '0-50%'],
            ['min' => 50, 'max' => 75, 'label' => '50-75%'],
            ['min' => 75, 'max' => 100, 'label' => '75-100%'],
            ['min' => 100, 'max' => 150, 'label' => '100-150%'],
            ['min' => 150, 'max' => 201, 'label' => '150%+'],
        ];

        $achievementDistribution = collect($ranges)->map(function ($range) use ($achievements) {
            $count = $achievements->filter(function ($val) use ($range) {
                return $val >= $range['min'] && $val < $range['max'];
            })->count();

            return [
                'range' => $range['label'],
                'count' => $count,
            ];
        })->toArray();

        // Averages and totals
        $totalKpis = (clone $baseQuery)->count();
        $averageAchievement = $achievements->isNotEmpty()
            ? round($achievements->avg(), 1)
            : 0;
        $overachievingCount = $achievements->filter(fn ($v) => $v > 100)->count();

        return [
            'byStatus' => $byStatus,
            'achievementDistribution' => $achievementDistribution,
            'averageAchievement' => $averageAchievement,
            'totalKpis' => $totalKpis,
            'overachievingCount' => $overachievingCount,
        ];
    }

    // =========================================================================
    // METRICS BY DEPARTMENT
    // =========================================================================

    /**
     * Get metrics breakdown by department.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{
     *     department: string,
     *     departmentId: int,
     *     evaluations: int,
     *     completedEvaluations: int,
     *     averageRating: float|null,
     *     developmentPlans: int,
     *     goals: int,
     *     goalsAchieved: int
     * }>
     */
    public function getMetricsByDepartment(
        ?array $departmentIds = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        // Get all departments (filtered if requested)
        $departmentQuery = \App\Models\Department::query()->active();
        if ($departmentIds !== null && count($departmentIds) > 0) {
            $departmentQuery->whereIn('id', $departmentIds);
        }

        $departments = $departmentQuery->orderBy('name')->get();

        return $departments->map(function ($department) use ($range) {
            $deptIds = [$department->id];

            // Evaluations
            $participantQuery = PerformanceCycleParticipant::query()
                ->included()
                ->whereHas('performanceCycleInstance', function ($q) use ($range) {
                    $q->whereBetween('end_date', [$range['start'], $range['end']]);
                })
                ->whereHas('employee', function ($q) use ($deptIds) {
                    $q->whereIn('department_id', $deptIds);
                });

            $evaluations = (clone $participantQuery)->count();
            $completedEvaluations = (clone $participantQuery)
                ->whereHas('evaluationSummary', function ($q) {
                    $q->whereNotNull('final_rating');
                })
                ->count();

            // Average rating
            $participantIds = (clone $participantQuery)->pluck('id');
            $ratings = EvaluationSummary::query()
                ->whereIn('performance_cycle_participant_id', $participantIds)
                ->whereNotNull('final_overall_score')
                ->pluck('final_overall_score');
            $averageRating = $ratings->isNotEmpty() ? round($ratings->avg(), 2) : null;

            // Development plans
            $developmentPlans = DevelopmentPlan::query()
                ->active()
                ->whereHas('employee', function ($q) use ($deptIds) {
                    $q->whereIn('department_id', $deptIds);
                })
                ->count();

            // Goals
            $goalQuery = Goal::query()
                ->whereHas('employee', function ($q) use ($deptIds) {
                    $q->whereIn('department_id', $deptIds);
                });

            $goals = (clone $goalQuery)->where('status', GoalStatus::Active)->count();
            $goalsAchieved = (clone $goalQuery)
                ->where('status', GoalStatus::Completed)
                ->whereBetween('completed_at', [$range['start'], $range['end']])
                ->count();

            return [
                'department' => $department->name,
                'departmentId' => $department->id,
                'evaluations' => $evaluations,
                'completedEvaluations' => $completedEvaluations,
                'averageRating' => $averageRating,
                'developmentPlans' => $developmentPlans,
                'goals' => $goals,
                'goalsAchieved' => $goalsAchieved,
            ];
        })->toArray();
    }
}
