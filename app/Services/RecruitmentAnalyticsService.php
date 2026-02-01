<?php

namespace App\Services;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Enums\JobRequisitionStatus;
use App\Enums\JobRequisitionUrgency;
use App\Enums\OfferStatus;
use App\Models\Department;
use App\Models\Interview;
use App\Models\InterviewPanelist;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\Offer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service class for Recruitment Analytics Dashboard calculations.
 *
 * Provides aggregated metrics for hiring funnel, time-to-fill, source effectiveness,
 * offer analytics, requisition analytics, interviewer performance, and hiring trends.
 */
class RecruitmentAnalyticsService
{
    /**
     * Get the date range for filtering.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getDateRange(?string $startDate, ?string $endDate): array
    {
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();
        $start = $startDate ? Carbon::parse($startDate) : $end->copy()->subDays(30);

        return ['start' => $start->startOfDay(), 'end' => $end->endOfDay()];
    }

    // =========================================================================
    // SUMMARY METRICS (Immediate Load)
    // =========================================================================

    /**
     * Get summary metrics for KPI cards.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     activeRequisitions: int,
     *     openPositions: int,
     *     totalApplications: int,
     *     avgTimeToFill: float|null,
     *     offerAcceptanceRate: float
     * }
     */
    public function getSummaryMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        // Active requisitions (approved, not yet filled)
        $activeRequisitionsQuery = JobRequisition::query()
            ->where('status', JobRequisitionStatus::Approved);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $activeRequisitionsQuery->whereIn('department_id', $departmentIds);
        }

        $activeRequisitions = $activeRequisitionsQuery->count();

        // Open positions (published job postings)
        $openPositionsQuery = JobPosting::query()
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('closed_at')
                    ->orWhere('closed_at', '>', now());
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $openPositionsQuery->whereIn('department_id', $departmentIds);
        }

        $openPositions = $openPositionsQuery->count();

        // Total applications in date range
        $appQuery = JobApplication::query()
            ->whereBetween('applied_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $appQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $totalApplications = $appQuery->count();

        // Average time to fill (from applied_at to hired_at)
        $hiredApps = (clone $appQuery)
            ->where('status', ApplicationStatus::Hired)
            ->whereNotNull('hired_at')
            ->select(['applied_at', 'hired_at'])
            ->get();

        $avgTimeToFill = null;
        if ($hiredApps->isNotEmpty()) {
            $totalDays = $hiredApps->sum(function ($app) {
                return $app->applied_at->diffInDays($app->hired_at);
            });
            $avgTimeToFill = round($totalDays / $hiredApps->count(), 1);
        }

        // Offer acceptance rate
        $hiredCount = (clone $appQuery)->where('status', ApplicationStatus::Hired)->count();
        $offeredCount = (clone $appQuery)
            ->whereIn('status', [ApplicationStatus::Offer, ApplicationStatus::Hired, ApplicationStatus::Rejected])
            ->whereNotNull('offer_at')
            ->count();

        $offerAcceptanceRate = $offeredCount > 0
            ? round(($hiredCount / $offeredCount) * 100, 1)
            : 0;

        return [
            'activeRequisitions' => $activeRequisitions,
            'openPositions' => $openPositions,
            'totalApplications' => $totalApplications,
            'avgTimeToFill' => $avgTimeToFill,
            'offerAcceptanceRate' => $offerAcceptanceRate,
        ];
    }

    // =========================================================================
    // FUNNEL METRICS
    // =========================================================================

    /**
     * Get hiring funnel metrics with conversion rates.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{
     *     stage: string,
     *     label: string,
     *     count: int,
     *     conversionRate: float|null,
     *     color: string
     * }>
     */
    public function getFunnelMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = JobApplication::query()
            ->whereBetween('applied_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        // Count applications that reached or passed each stage
        $stages = [
            ApplicationStatus::Applied,
            ApplicationStatus::Screening,
            ApplicationStatus::Interview,
            ApplicationStatus::Assessment,
            ApplicationStatus::Offer,
            ApplicationStatus::Hired,
        ];

        $colors = [
            'applied' => '#3b82f6',
            'screening' => '#8b5cf6',
            'interview' => '#06b6d4',
            'assessment' => '#f59e0b',
            'offer' => '#22c55e',
            'hired' => '#10b981',
        ];

        $funnel = [];
        $previousCount = null;

        foreach ($stages as $stage) {
            // Count applications that have reached this stage or later
            $count = (clone $query)
                ->where(function ($q) use ($stage) {
                    // Current status is this stage or later
                    $q->whereIn('status', $this->getStagesAtOrAfter($stage))
                        ->orWhereNotNull($this->getStageTimestampColumn($stage));
                })
                ->count();

            $conversionRate = $previousCount !== null && $previousCount > 0
                ? round(($count / $previousCount) * 100, 1)
                : null;

            $funnel[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $count,
                'conversionRate' => $conversionRate,
                'color' => $colors[$stage->value] ?? '#64748b',
            ];

            $previousCount = $count;
        }

        return $funnel;
    }

    /**
     * Get all stages at or after the given stage.
     *
     * @return array<ApplicationStatus>
     */
    protected function getStagesAtOrAfter(ApplicationStatus $stage): array
    {
        $pipeline = [
            ApplicationStatus::Applied,
            ApplicationStatus::Screening,
            ApplicationStatus::Interview,
            ApplicationStatus::Assessment,
            ApplicationStatus::Offer,
            ApplicationStatus::Hired,
        ];

        $index = array_search($stage, $pipeline, true);

        if ($index === false) {
            return [$stage];
        }

        return array_slice($pipeline, $index);
    }

    /**
     * Get the timestamp column for a stage.
     */
    protected function getStageTimestampColumn(ApplicationStatus $stage): string
    {
        return match ($stage) {
            ApplicationStatus::Applied => 'applied_at',
            ApplicationStatus::Screening => 'screening_at',
            ApplicationStatus::Interview => 'interview_at',
            ApplicationStatus::Assessment => 'assessment_at',
            ApplicationStatus::Offer => 'offer_at',
            ApplicationStatus::Hired => 'hired_at',
            ApplicationStatus::Rejected => 'rejected_at',
            ApplicationStatus::Withdrawn => 'withdrawn_at',
        };
    }

    /**
     * Get dropout analysis by stage.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{
     *     stage: string,
     *     label: string,
     *     rejected: int,
     *     withdrawn: int,
     *     topRejectionReasons: array<array{reason: string, count: int}>
     * }>
     */
    public function getDropoutAnalysis(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $stages = [
            ApplicationStatus::Screening,
            ApplicationStatus::Interview,
            ApplicationStatus::Assessment,
            ApplicationStatus::Offer,
        ];

        $result = [];

        foreach ($stages as $stage) {
            $baseQuery = JobApplication::query()
                ->whereBetween('applied_at', [$range['start'], $range['end']])
                ->whereNotNull($this->getStageTimestampColumn($stage));

            if ($departmentIds !== null && count($departmentIds) > 0) {
                $baseQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                    $q->whereIn('department_id', $departmentIds);
                });
            }

            $rejected = (clone $baseQuery)
                ->where('status', ApplicationStatus::Rejected)
                ->count();

            $withdrawn = (clone $baseQuery)
                ->where('status', ApplicationStatus::Withdrawn)
                ->count();

            // Get top rejection reasons at this stage
            $topRejectionReasons = (clone $baseQuery)
                ->where('status', ApplicationStatus::Rejected)
                ->whereNotNull('rejection_reason')
                ->select('rejection_reason', DB::raw('COUNT(*) as count'))
                ->groupBy('rejection_reason')
                ->orderByDesc('count')
                ->limit(3)
                ->get()
                ->map(fn ($row) => [
                    'reason' => $row->rejection_reason,
                    'count' => (int) $row->count,
                ])
                ->toArray();

            $result[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'rejected' => $rejected,
                'withdrawn' => $withdrawn,
                'topRejectionReasons' => $topRejectionReasons,
            ];
        }

        return $result;
    }

    // =========================================================================
    // TIME-TO-FILL METRICS
    // =========================================================================

    /**
     * Get time-to-fill metrics by stage.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     byStage: array<array{stage: string, label: string, avgDays: float|null}>,
     *     bottleneck: string|null,
     *     totalAvgDays: float|null
     * }
     */
    public function getTimeToFillMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = JobApplication::query()
            ->whereBetween('applied_at', [$range['start'], $range['end']])
            ->where('status', ApplicationStatus::Hired)
            ->whereNotNull('hired_at');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $applications = $query->get();

        $stages = [
            ['from' => 'applied_at', 'to' => 'screening_at', 'stage' => 'applied', 'label' => 'Applied to Screening'],
            ['from' => 'screening_at', 'to' => 'interview_at', 'stage' => 'screening', 'label' => 'Screening to Interview'],
            ['from' => 'interview_at', 'to' => 'assessment_at', 'stage' => 'interview', 'label' => 'Interview to Assessment'],
            ['from' => 'assessment_at', 'to' => 'offer_at', 'stage' => 'assessment', 'label' => 'Assessment to Offer'],
            ['from' => 'offer_at', 'to' => 'hired_at', 'stage' => 'offer', 'label' => 'Offer to Hired'],
        ];

        $byStage = [];
        $maxDays = 0;
        $bottleneck = null;

        foreach ($stages as $stageConfig) {
            $validApps = $applications->filter(fn ($app) => $app->{$stageConfig['from']} !== null && $app->{$stageConfig['to']} !== null
            );

            $avgDays = null;
            if ($validApps->isNotEmpty()) {
                $totalDays = $validApps->sum(function ($app) use ($stageConfig) {
                    return $app->{$stageConfig['from']}->diffInDays($app->{$stageConfig['to']});
                });
                $avgDays = round($totalDays / $validApps->count(), 1);

                if ($avgDays > $maxDays) {
                    $maxDays = $avgDays;
                    $bottleneck = $stageConfig['label'];
                }
            }

            $byStage[] = [
                'stage' => $stageConfig['stage'],
                'label' => $stageConfig['label'],
                'avgDays' => $avgDays,
            ];
        }

        // Total average days from applied to hired
        $totalAvgDays = null;
        if ($applications->isNotEmpty()) {
            $totalDays = $applications->sum(fn ($app) => $app->applied_at->diffInDays($app->hired_at));
            $totalAvgDays = round($totalDays / $applications->count(), 1);
        }

        return [
            'byStage' => $byStage,
            'bottleneck' => $bottleneck,
            'totalAvgDays' => $totalAvgDays,
        ];
    }

    /**
     * Get time-to-fill trend over time.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{month: string, avgDays: float}>
     */
    public function getTimeToFillTrend(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = JobApplication::query()
            ->whereBetween('hired_at', [$range['start'], $range['end']])
            ->where('status', ApplicationStatus::Hired)
            ->whereNotNull('hired_at')
            ->select([
                DB::raw("DATE_FORMAT(hired_at, '%Y-%m') as month"),
                'applied_at',
                'hired_at',
            ]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $applications = $query->get();

        return $applications
            ->groupBy('month')
            ->map(function ($apps, $month) {
                $totalDays = $apps->sum(fn ($app) => $app->applied_at->diffInDays($app->hired_at));

                return [
                    'month' => $month,
                    'avgDays' => round($totalDays / $apps->count(), 1),
                ];
            })
            ->sortBy('month')
            ->values()
            ->toArray();
    }

    /**
     * Get time-to-fill by department.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{department: string, departmentId: int, avgDays: float, count: int}>
     */
    public function getTimeToFillByDepartment(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = JobApplication::query()
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->join('departments', 'job_postings.department_id', '=', 'departments.id')
            ->whereBetween('job_applications.hired_at', [$range['start'], $range['end']])
            ->where('job_applications.status', ApplicationStatus::Hired)
            ->whereNotNull('job_applications.hired_at')
            ->select([
                'departments.id as department_id',
                'departments.name as department',
                'job_applications.applied_at',
                'job_applications.hired_at',
            ]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereIn('departments.id', $departmentIds);
        }

        return $query->get()
            ->groupBy('department_id')
            ->map(function ($apps, $deptId) {
                $first = $apps->first();
                $totalDays = $apps->sum(fn ($app) => Carbon::parse($app->applied_at)->diffInDays(Carbon::parse($app->hired_at)));

                return [
                    'department' => $first->department,
                    'departmentId' => (int) $deptId,
                    'avgDays' => round($totalDays / $apps->count(), 1),
                    'count' => $apps->count(),
                ];
            })
            ->sortBy('avgDays')
            ->values()
            ->toArray();
    }

    // =========================================================================
    // SOURCE EFFECTIVENESS
    // =========================================================================

    /**
     * Get source effectiveness metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{
     *     source: string,
     *     label: string,
     *     applications: int,
     *     hires: int,
     *     hireRate: float,
     *     color: string
     * }>
     */
    public function getSourceEffectiveness(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $colors = [
            'careers_page' => '#3b82f6',
            'manual_entry' => '#8b5cf6',
            'referral' => '#22c55e',
        ];

        $results = [];

        foreach (ApplicationSource::cases() as $source) {
            $query = JobApplication::query()
                ->whereBetween('applied_at', [$range['start'], $range['end']])
                ->where('source', $source);

            if ($departmentIds !== null && count($departmentIds) > 0) {
                $query->whereHas('jobPosting', function ($q) use ($departmentIds) {
                    $q->whereIn('department_id', $departmentIds);
                });
            }

            $applications = (clone $query)->count();
            $hires = (clone $query)->where('status', ApplicationStatus::Hired)->count();

            $results[] = [
                'source' => $source->value,
                'label' => $source->label(),
                'applications' => $applications,
                'hires' => $hires,
                'hireRate' => $applications > 0 ? round(($hires / $applications) * 100, 1) : 0,
                'color' => $colors[$source->value] ?? '#64748b',
            ];
        }

        return $results;
    }

    /**
     * Get source quality metrics (pass-through rates per source).
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{
     *     source: string,
     *     label: string,
     *     screeningPassRate: float,
     *     interviewPassRate: float,
     *     offerAcceptRate: float
     * }>
     */
    public function getSourceQualityMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $results = [];

        foreach (ApplicationSource::cases() as $source) {
            $baseQuery = JobApplication::query()
                ->whereBetween('applied_at', [$range['start'], $range['end']])
                ->where('source', $source);

            if ($departmentIds !== null && count($departmentIds) > 0) {
                $baseQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                    $q->whereIn('department_id', $departmentIds);
                });
            }

            $totalApplied = (clone $baseQuery)->count();
            $reachedScreening = (clone $baseQuery)->whereNotNull('screening_at')->count();
            $reachedInterview = (clone $baseQuery)->whereNotNull('interview_at')->count();
            $reachedOffer = (clone $baseQuery)->whereNotNull('offer_at')->count();
            $hired = (clone $baseQuery)->where('status', ApplicationStatus::Hired)->count();

            $results[] = [
                'source' => $source->value,
                'label' => $source->label(),
                'screeningPassRate' => $totalApplied > 0 ? round(($reachedScreening / $totalApplied) * 100, 1) : 0,
                'interviewPassRate' => $reachedScreening > 0 ? round(($reachedInterview / $reachedScreening) * 100, 1) : 0,
                'offerAcceptRate' => $reachedOffer > 0 ? round(($hired / $reachedOffer) * 100, 1) : 0,
            ];
        }

        return $results;
    }

    // =========================================================================
    // OFFER ANALYTICS
    // =========================================================================

    /**
     * Get offer metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     total: int,
     *     accepted: int,
     *     declined: int,
     *     pending: int,
     *     acceptanceRate: float,
     *     avgResponseDays: float|null
     * }
     */
    public function getOfferMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = Offer::query()
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobApplication.jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $total = (clone $query)->count();
        $accepted = (clone $query)->where('status', OfferStatus::Accepted)->count();
        $declined = (clone $query)->where('status', OfferStatus::Declined)->count();
        $pending = (clone $query)->whereIn('status', [OfferStatus::Sent, OfferStatus::Viewed])->count();

        $acceptanceRate = ($accepted + $declined) > 0
            ? round(($accepted / ($accepted + $declined)) * 100, 1)
            : 0;

        // Average response time
        $respondedOffers = (clone $query)
            ->whereIn('status', [OfferStatus::Accepted, OfferStatus::Declined])
            ->whereNotNull('sent_at')
            ->get();

        $avgResponseDays = null;
        if ($respondedOffers->isNotEmpty()) {
            $totalDays = $respondedOffers->sum(function ($offer) {
                $respondedAt = $offer->accepted_at ?? $offer->declined_at;

                return $offer->sent_at->diffInDays($respondedAt);
            });
            $avgResponseDays = round($totalDays / $respondedOffers->count(), 1);
        }

        return [
            'total' => $total,
            'accepted' => $accepted,
            'declined' => $declined,
            'pending' => $pending,
            'acceptanceRate' => $acceptanceRate,
            'avgResponseDays' => $avgResponseDays,
        ];
    }

    /**
     * Get offer acceptance trend.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{month: string, total: int, accepted: int, acceptanceRate: float}>
     */
    public function getOfferAcceptanceTrend(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = Offer::query()
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                'status',
            ]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobApplication.jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        return $query->get()
            ->groupBy('month')
            ->map(function ($offers, $month) {
                $total = $offers->count();
                $accepted = $offers->where('status', OfferStatus::Accepted)->count();
                $declined = $offers->where('status', OfferStatus::Declined)->count();
                $decided = $accepted + $declined;

                return [
                    'month' => $month,
                    'total' => $total,
                    'accepted' => $accepted,
                    'acceptanceRate' => $decided > 0 ? round(($accepted / $decided) * 100, 1) : 0,
                ];
            })
            ->sortBy('month')
            ->values()
            ->toArray();
    }

    /**
     * Get offer decline reasons breakdown.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{reason: string, count: int, percentage: float}>
     */
    public function getOfferDeclineReasons(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = Offer::query()
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->where('status', OfferStatus::Declined)
            ->whereNotNull('decline_reason')
            ->select([
                'decline_reason',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('decline_reason')
            ->orderByDesc('count');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobApplication.jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $results = $query->get();
        $total = $results->sum('count');

        return $results->map(fn ($row) => [
            'reason' => $row->decline_reason,
            'count' => (int) $row->count,
            'percentage' => $total > 0 ? round(($row->count / $total) * 100, 1) : 0,
        ])->toArray();
    }

    // =========================================================================
    // REQUISITION ANALYTICS
    // =========================================================================

    /**
     * Get requisition metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     open: int,
     *     approved: int,
     *     pending: int,
     *     rejected: int,
     *     fillRate: float,
     *     avgApprovalDays: float|null
     * }
     */
    public function getRequisitionMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = JobRequisition::query()
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereIn('department_id', $departmentIds);
        }

        $open = (clone $query)->whereIn('status', [JobRequisitionStatus::Draft, JobRequisitionStatus::Pending])->count();
        $approved = (clone $query)->where('status', JobRequisitionStatus::Approved)->count();
        $pending = (clone $query)->where('status', JobRequisitionStatus::Pending)->count();
        $rejected = (clone $query)->where('status', JobRequisitionStatus::Rejected)->count();

        // Fill rate: requisitions that have at least one hire
        $filledRequisitions = JobRequisition::query()
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->where('status', JobRequisitionStatus::Approved)
            ->whereHas('jobPostings.jobApplications', function ($q) {
                $q->where('status', ApplicationStatus::Hired);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $filledRequisitions->whereIn('department_id', $departmentIds);
        }

        $fillRate = $approved > 0 ? round(($filledRequisitions->count() / $approved) * 100, 1) : 0;

        // Average approval time
        $approvedReqs = (clone $query)
            ->where('status', JobRequisitionStatus::Approved)
            ->whereNotNull('submitted_at')
            ->whereNotNull('approved_at')
            ->get();

        $avgApprovalDays = null;
        if ($approvedReqs->isNotEmpty()) {
            $totalDays = $approvedReqs->sum(fn ($req) => $req->submitted_at->diffInDays($req->approved_at));
            $avgApprovalDays = round($totalDays / $approvedReqs->count(), 1);
        }

        return [
            'open' => $open,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'fillRate' => $fillRate,
            'avgApprovalDays' => $avgApprovalDays,
        ];
    }

    /**
     * Get requisitions by urgency.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{urgency: string, label: string, count: int, color: string}>
     */
    public function getRequisitionsByUrgency(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $results = [];

        foreach (JobRequisitionUrgency::cases() as $urgency) {
            $query = JobRequisition::query()
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->where('urgency', $urgency)
                ->whereIn('status', [JobRequisitionStatus::Draft, JobRequisitionStatus::Pending, JobRequisitionStatus::Approved]);

            if ($departmentIds !== null && count($departmentIds) > 0) {
                $query->whereIn('department_id', $departmentIds);
            }

            $results[] = [
                'urgency' => $urgency->value,
                'label' => $urgency->label(),
                'count' => $query->count(),
                'color' => match ($urgency) {
                    JobRequisitionUrgency::Low => '#64748b',
                    JobRequisitionUrgency::Normal => '#3b82f6',
                    JobRequisitionUrgency::High => '#f59e0b',
                    JobRequisitionUrgency::Urgent => '#ef4444',
                },
            ];
        }

        return $results;
    }

    /**
     * Get headcount vs hires by department.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{
     *     department: string,
     *     departmentId: int,
     *     requestedHeadcount: int,
     *     hires: int,
     *     variance: int,
     *     variancePercent: float
     * }>
     */
    public function getHeadcountVsHires(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        // Get approved requisitions with headcount
        $requisitionsQuery = JobRequisition::query()
            ->join('departments', 'job_requisitions.department_id', '=', 'departments.id')
            ->whereBetween('job_requisitions.created_at', [$range['start'], $range['end']])
            ->where('job_requisitions.status', JobRequisitionStatus::Approved)
            ->select([
                'departments.id as department_id',
                'departments.name as department',
                DB::raw('SUM(job_requisitions.headcount) as requested_headcount'),
            ])
            ->groupBy('departments.id', 'departments.name');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $requisitionsQuery->whereIn('departments.id', $departmentIds);
        }

        $requisitions = $requisitionsQuery->get()->keyBy('department_id');

        // Get hires by department
        $hiresQuery = JobApplication::query()
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->join('departments', 'job_postings.department_id', '=', 'departments.id')
            ->whereBetween('job_applications.hired_at', [$range['start'], $range['end']])
            ->where('job_applications.status', ApplicationStatus::Hired)
            ->select([
                'departments.id as department_id',
                'departments.name as department',
                DB::raw('COUNT(*) as hires'),
            ])
            ->groupBy('departments.id', 'departments.name');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $hiresQuery->whereIn('departments.id', $departmentIds);
        }

        $hires = $hiresQuery->get()->keyBy('department_id');

        // Merge data
        $departmentIds = $requisitions->keys()->merge($hires->keys())->unique();

        return $departmentIds->map(function ($deptId) use ($requisitions, $hires) {
            $req = $requisitions->get($deptId);
            $hire = $hires->get($deptId);

            $requestedHeadcount = (int) ($req?->requested_headcount ?? 0);
            $hiresCount = (int) ($hire?->hires ?? 0);
            $variance = $hiresCount - $requestedHeadcount;

            return [
                'department' => $req?->department ?? $hire?->department ?? 'Unknown',
                'departmentId' => (int) $deptId,
                'requestedHeadcount' => $requestedHeadcount,
                'hires' => $hiresCount,
                'variance' => $variance,
                'variancePercent' => $requestedHeadcount > 0 ? round(($hiresCount / $requestedHeadcount) * 100, 1) : 0,
            ];
        })->values()->toArray();
    }

    // =========================================================================
    // INTERVIEWER PERFORMANCE
    // =========================================================================

    /**
     * Get interview metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     total: int,
     *     completed: int,
     *     cancelled: int,
     *     noShows: int,
     *     completionRate: float,
     *     avgDurationMinutes: float|null
     * }
     */
    public function getInterviewMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = Interview::query()
            ->whereBetween('scheduled_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobApplication.jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', InterviewStatus::Completed)->count();
        $cancelled = (clone $query)->where('status', InterviewStatus::Cancelled)->count();
        $noShows = (clone $query)->where('status', InterviewStatus::NoShow)->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        // Average duration
        $completedInterviews = (clone $query)
            ->where('status', InterviewStatus::Completed)
            ->whereNotNull('duration_minutes')
            ->avg('duration_minutes');

        return [
            'total' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'noShows' => $noShows,
            'completionRate' => $completionRate,
            'avgDurationMinutes' => $completedInterviews !== null ? round($completedInterviews, 0) : null,
        ];
    }

    /**
     * Get interviewer leaderboard.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{
     *     employeeId: int,
     *     name: string,
     *     totalInterviews: int,
     *     completedInterviews: int,
     *     avgRating: float|null,
     *     passThroughRate: float
     * }>
     */
    public function getInterviewerLeaderboard(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null,
        int $limit = 10
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = InterviewPanelist::query()
            ->join('interviews', 'interview_panelists.interview_id', '=', 'interviews.id')
            ->join('employees', 'interview_panelists.employee_id', '=', 'employees.id')
            ->whereBetween('interviews.scheduled_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('interview.jobApplication.jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $panelistStats = $query
            ->select([
                'employees.id as employee_id',
                DB::raw("CONCAT(employees.first_name, ' ', employees.last_name) as name"),
                DB::raw('COUNT(*) as total_interviews'),
                DB::raw("SUM(CASE WHEN interviews.status = 'completed' THEN 1 ELSE 0 END) as completed_interviews"),
                DB::raw('AVG(interview_panelists.rating) as avg_rating'),
            ])
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')
            ->orderByDesc('total_interviews')
            ->limit($limit)
            ->get();

        return $panelistStats->map(function ($stat) use ($range, $departmentIds) {
            // Calculate pass-through rate (candidates who progressed after interview)
            $interviewedCandidates = InterviewPanelist::query()
                ->join('interviews', 'interview_panelists.interview_id', '=', 'interviews.id')
                ->join('job_applications', 'interviews.job_application_id', '=', 'job_applications.id')
                ->where('interview_panelists.employee_id', $stat->employee_id)
                ->whereBetween('interviews.scheduled_at', [$range['start'], $range['end']])
                ->where('interviews.status', InterviewStatus::Completed);

            if ($departmentIds !== null && count($departmentIds) > 0) {
                $interviewedCandidates->whereHas('interview.jobApplication.jobPosting', function ($q) use ($departmentIds) {
                    $q->whereIn('department_id', $departmentIds);
                });
            }

            $total = (clone $interviewedCandidates)->count();
            $progressed = (clone $interviewedCandidates)
                ->whereIn('job_applications.status', [
                    ApplicationStatus::Assessment,
                    ApplicationStatus::Offer,
                    ApplicationStatus::Hired,
                ])
                ->orWhereNotNull('job_applications.assessment_at')
                ->count();

            return [
                'employeeId' => (int) $stat->employee_id,
                'name' => $stat->name,
                'totalInterviews' => (int) $stat->total_interviews,
                'completedInterviews' => (int) $stat->completed_interviews,
                'avgRating' => $stat->avg_rating !== null ? round((float) $stat->avg_rating, 1) : null,
                'passThroughRate' => $total > 0 ? round(($progressed / $total) * 100, 1) : 0,
            ];
        })->toArray();
    }

    /**
     * Get interview scheduling metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     avgDaysToSchedule: float|null,
     *     scheduledThisWeek: int,
     *     scheduledNextWeek: int,
     *     rescheduledCount: int
     * }
     */
    public function getInterviewSchedulingMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = Interview::query()
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobApplication.jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        // Average days to schedule (from application interview_at to interview scheduled_at)
        $interviews = (clone $query)
            ->with('jobApplication')
            ->whereNotNull('scheduled_at')
            ->get();

        $avgDaysToSchedule = null;
        if ($interviews->isNotEmpty()) {
            $validInterviews = $interviews->filter(fn ($i) => $i->jobApplication?->interview_at !== null);
            if ($validInterviews->isNotEmpty()) {
                $totalDays = $validInterviews->sum(fn ($i) => $i->jobApplication->interview_at->diffInDays($i->scheduled_at));
                $avgDaysToSchedule = round($totalDays / $validInterviews->count(), 1);
            }
        }

        // Scheduled this week
        $thisWeekStart = Carbon::now()->startOfWeek();
        $thisWeekEnd = Carbon::now()->endOfWeek();

        $scheduledThisWeek = Interview::query()
            ->whereBetween('scheduled_at', [$thisWeekStart, $thisWeekEnd])
            ->when($departmentIds !== null && count($departmentIds) > 0, function ($q) use ($departmentIds) {
                $q->whereHas('jobApplication.jobPosting', function ($sq) use ($departmentIds) {
                    $sq->whereIn('department_id', $departmentIds);
                });
            })
            ->count();

        // Scheduled next week
        $nextWeekStart = Carbon::now()->addWeek()->startOfWeek();
        $nextWeekEnd = Carbon::now()->addWeek()->endOfWeek();

        $scheduledNextWeek = Interview::query()
            ->whereBetween('scheduled_at', [$nextWeekStart, $nextWeekEnd])
            ->when($departmentIds !== null && count($departmentIds) > 0, function ($q) use ($departmentIds) {
                $q->whereHas('jobApplication.jobPosting', function ($sq) use ($departmentIds) {
                    $sq->whereIn('department_id', $departmentIds);
                });
            })
            ->count();

        // Count rescheduled (cancelled interviews that have another interview for same application)
        $rescheduledCount = (clone $query)
            ->where('status', InterviewStatus::Cancelled)
            ->whereHas('jobApplication.interviews', function ($q) {
                $q->whereIn('status', [InterviewStatus::Scheduled, InterviewStatus::Confirmed, InterviewStatus::Completed]);
            })
            ->count();

        return [
            'avgDaysToSchedule' => $avgDaysToSchedule,
            'scheduledThisWeek' => $scheduledThisWeek,
            'scheduledNextWeek' => $scheduledNextWeek,
            'rescheduledCount' => $rescheduledCount,
        ];
    }

    // =========================================================================
    // HIRING TRENDS
    // =========================================================================

    /**
     * Get hiring velocity trend.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{month: string, applications: int, hires: int, velocity: float}>
     */
    public function getHiringVelocityTrend(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        // Applications by month
        $applicationsQuery = JobApplication::query()
            ->whereBetween('applied_at', [$range['start'], $range['end']])
            ->select([
                DB::raw("DATE_FORMAT(applied_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy(DB::raw("DATE_FORMAT(applied_at, '%Y-%m')"));

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $applicationsQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $applications = $applicationsQuery->get()->keyBy('month');

        // Hires by month
        $hiresQuery = JobApplication::query()
            ->whereBetween('hired_at', [$range['start'], $range['end']])
            ->where('status', ApplicationStatus::Hired)
            ->select([
                DB::raw("DATE_FORMAT(hired_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy(DB::raw("DATE_FORMAT(hired_at, '%Y-%m')"));

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $hiresQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $hires = $hiresQuery->get()->keyBy('month');

        // Combine and calculate velocity
        $months = $applications->keys()->merge($hires->keys())->unique()->sort();

        return $months->map(function ($month) use ($applications, $hires) {
            $appCount = (int) ($applications->get($month)?->count ?? 0);
            $hireCount = (int) ($hires->get($month)?->count ?? 0);

            return [
                'month' => $month,
                'applications' => $appCount,
                'hires' => $hireCount,
                'velocity' => $appCount > 0 ? round(($hireCount / $appCount) * 100, 1) : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Get seasonal hiring patterns.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{month: int, monthName: string, avgApplications: float, avgHires: float}>
     */
    public function getSeasonalPatterns(?array $departmentIds = null): array
    {
        // Look at last 2 years of data for patterns
        $twoYearsAgo = Carbon::now()->subYears(2)->startOfYear();

        $hiresQuery = JobApplication::query()
            ->where('hired_at', '>=', $twoYearsAgo)
            ->where('status', ApplicationStatus::Hired)
            ->select([
                DB::raw('MONTH(hired_at) as month'),
                DB::raw('YEAR(hired_at) as year'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy(DB::raw('YEAR(hired_at)'), DB::raw('MONTH(hired_at)'));

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $hiresQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $hiresByMonth = $hiresQuery->get();

        $applicationsQuery = JobApplication::query()
            ->where('applied_at', '>=', $twoYearsAgo)
            ->select([
                DB::raw('MONTH(applied_at) as month'),
                DB::raw('YEAR(applied_at) as year'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy(DB::raw('YEAR(applied_at)'), DB::raw('MONTH(applied_at)'));

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $applicationsQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $applicationsByMonth = $applicationsQuery->get();

        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        return collect(range(1, 12))->map(function ($month) use ($hiresByMonth, $applicationsByMonth, $monthNames) {
            $monthHires = $hiresByMonth->where('month', $month);
            $monthApps = $applicationsByMonth->where('month', $month);

            $yearsWithHires = $monthHires->count();
            $yearsWithApps = $monthApps->count();

            return [
                'month' => $month,
                'monthName' => $monthNames[$month],
                'avgApplications' => $yearsWithApps > 0 ? round($monthApps->sum('count') / $yearsWithApps, 1) : 0,
                'avgHires' => $yearsWithHires > 0 ? round($monthHires->sum('count') / $yearsWithHires, 1) : 0,
            ];
        })->toArray();
    }

    // =========================================================================
    // DEPARTMENTS LIST
    // =========================================================================

    /**
     * Get list of active departments for filtering.
     *
     * @return array<array{id: int, name: string}>
     */
    public function getDepartments(): array
    {
        return Department::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }
}
