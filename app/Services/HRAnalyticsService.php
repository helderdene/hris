<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DtrStatus;
use App\Enums\LeaveApplicationStatus;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EvaluationSummary;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\PayrollEntry;
use App\Models\PerformanceCycleParticipant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service class for HR Analytics Dashboard calculations.
 *
 * Provides aggregated metrics across headcount, attendance, leave,
 * compensation, recruitment, and performance areas.
 */
class HRAnalyticsService
{
    public function __construct(
        protected EmployeeDashboardService $employeeDashboardService
    ) {}

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
    // HEADCOUNT METRICS (delegated to EmployeeDashboardService)
    // =========================================================================

    /**
     * Get headcount metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{total: int, active: int, newHires: int, separations: int}
     */
    public function getHeadcountMetrics(?array $departmentIds = null): array
    {
        $baseQuery = Employee::query();

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $baseQuery->whereIn('department_id', $departmentIds);
        }

        $total = (clone $baseQuery)->count();
        $active = (clone $baseQuery)->active()->count();

        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        $newHiresQuery = (clone $baseQuery)
            ->whereBetween('hire_date', [$currentMonthStart, $currentMonthEnd]);

        $separationsQuery = (clone $baseQuery)
            ->whereBetween('termination_date', [$currentMonthStart, $currentMonthEnd])
            ->whereNotNull('termination_date');

        return [
            'total' => $total,
            'active' => $active,
            'newHires' => $newHiresQuery->count(),
            'separations' => $separationsQuery->count(),
        ];
    }

    // =========================================================================
    // ATTENDANCE METRICS
    // =========================================================================

    /**
     * Get attendance metrics for the date range.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     attendanceRate: float,
     *     presentCount: int,
     *     absentCount: int,
     *     lateCount: int,
     *     totalRecords: int
     * }
     */
    public function getAttendanceMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = DailyTimeRecord::query()
            ->whereBetween('date', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $totalRecords = (clone $query)->count();
        $presentCount = (clone $query)->where('status', DtrStatus::Present)->count();
        $absentCount = (clone $query)->where('status', DtrStatus::Absent)->count();
        $lateCount = (clone $query)->where('late_minutes', '>', 0)->count();

        $attendanceRate = $totalRecords > 0
            ? round(($presentCount / $totalRecords) * 100, 1)
            : 0;

        return [
            'attendanceRate' => $attendanceRate,
            'presentCount' => $presentCount,
            'absentCount' => $absentCount,
            'lateCount' => $lateCount,
            'totalRecords' => $totalRecords,
        ];
    }

    /**
     * Get attendance trend data grouped by date.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{date: string, rate: float, present: int, absent: int}>
     */
    public function getAttendanceTrend(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = DailyTimeRecord::query()
            ->whereBetween('date', [$range['start'], $range['end']])
            ->select([
                'date',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent"),
            ])
            ->groupBy('date')
            ->orderBy('date');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        return $query->get()->map(function ($row) {
            $total = (int) $row->total;
            $present = (int) $row->present;

            return [
                'date' => $row->date->format('Y-m-d'),
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
                'present' => $present,
                'absent' => (int) $row->absent,
            ];
        })->toArray();
    }

    /**
     * Get attendance by department.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{department: string, rate: float, present: int, total: int}>
     */
    public function getAttendanceByDepartment(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = DailyTimeRecord::query()
            ->join('employees', 'daily_time_records.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->whereBetween('daily_time_records.date', [$range['start'], $range['end']])
            ->select([
                'departments.name as department',
                'departments.id as department_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN daily_time_records.status = 'present' THEN 1 ELSE 0 END) as present"),
            ])
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('departments.name');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereIn('departments.id', $departmentIds);
        }

        return $query->get()->map(function ($row) {
            $total = (int) $row->total;
            $present = (int) $row->present;

            return [
                'department' => $row->department,
                'departmentId' => $row->department_id,
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
                'present' => $present,
                'total' => $total,
            ];
        })->toArray();
    }

    // =========================================================================
    // LEAVE METRICS
    // =========================================================================

    /**
     * Get leave utilization metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     totalApplications: int,
     *     approvedCount: int,
     *     pendingCount: int,
     *     rejectedCount: int,
     *     totalDaysUsed: float,
     *     approvalRate: float
     * }
     */
    public function getLeaveMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = LeaveApplication::query()
            ->where(function ($q) use ($range) {
                $q->whereBetween('start_date', [$range['start'], $range['end']])
                    ->orWhereBetween('end_date', [$range['start'], $range['end']]);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $totalApplications = (clone $query)->count();
        $approvedCount = (clone $query)->where('status', LeaveApplicationStatus::Approved)->count();
        $pendingCount = (clone $query)->where('status', LeaveApplicationStatus::Pending)->count();
        $rejectedCount = (clone $query)->where('status', LeaveApplicationStatus::Rejected)->count();

        $totalDaysUsed = (clone $query)
            ->where('status', LeaveApplicationStatus::Approved)
            ->sum('total_days');

        $decidedCount = $approvedCount + $rejectedCount;
        $approvalRate = $decidedCount > 0
            ? round(($approvedCount / $decidedCount) * 100, 1)
            : 0;

        return [
            'totalApplications' => $totalApplications,
            'approvedCount' => $approvedCount,
            'pendingCount' => $pendingCount,
            'rejectedCount' => $rejectedCount,
            'totalDaysUsed' => (float) $totalDaysUsed,
            'approvalRate' => $approvalRate,
        ];
    }

    /**
     * Get leave breakdown by type.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{type: string, count: int, days: float, color: string}>
     */
    public function getLeaveTypeBreakdown(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = LeaveApplication::query()
            ->join('leave_types', 'leave_applications.leave_type_id', '=', 'leave_types.id')
            ->where('leave_applications.status', LeaveApplicationStatus::Approved)
            ->where(function ($q) use ($range) {
                $q->whereBetween('leave_applications.start_date', [$range['start'], $range['end']])
                    ->orWhereBetween('leave_applications.end_date', [$range['start'], $range['end']]);
            })
            ->select([
                'leave_types.name as type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(leave_applications.total_days) as days'),
            ])
            ->groupBy('leave_types.id', 'leave_types.name')
            ->orderByDesc('count');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $colors = ['#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];

        return $query->get()->map(function ($row, $index) use ($colors) {
            return [
                'type' => $row->type,
                'count' => (int) $row->count,
                'days' => (float) $row->days,
                'color' => $colors[$index % count($colors)],
            ];
        })->toArray();
    }

    // =========================================================================
    // COMPENSATION METRICS
    // =========================================================================

    /**
     * Get compensation metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     totalExpense: float,
     *     averageSalary: float,
     *     totalGrossPay: float,
     *     totalDeductions: float,
     *     employeeCount: int
     * }
     */
    public function getCompensationMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = PayrollEntry::query()
            ->whereHas('payrollPeriod', function ($q) use ($range) {
                $q->whereBetween('cutoff_end', [$range['start'], $range['end']]);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $result = (clone $query)->selectRaw('
            SUM(net_pay) as total_net_pay,
            SUM(gross_pay) as total_gross_pay,
            SUM(total_deductions) as total_deductions,
            AVG(basic_salary_snapshot) as avg_salary,
            COUNT(DISTINCT employee_id) as employee_count
        ')->first();

        return [
            'totalExpense' => (float) ($result->total_gross_pay ?? 0),
            'averageSalary' => round((float) ($result->avg_salary ?? 0), 2),
            'totalGrossPay' => (float) ($result->total_gross_pay ?? 0),
            'totalDeductions' => (float) ($result->total_deductions ?? 0),
            'employeeCount' => (int) ($result->employee_count ?? 0),
        ];
    }

    /**
     * Get salary distribution by bands.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{band: string, count: int, min: float, max: float}>
     */
    public function getSalaryDistribution(?array $departmentIds = null): array
    {
        $query = Employee::query()->active()->whereNotNull('basic_salary');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereIn('department_id', $departmentIds);
        }

        $employees = $query->pluck('basic_salary');

        if ($employees->isEmpty()) {
            return [];
        }

        // Define salary bands
        $bands = [
            ['min' => 0, 'max' => 15000, 'label' => 'Below 15K'],
            ['min' => 15000, 'max' => 25000, 'label' => '15K-25K'],
            ['min' => 25000, 'max' => 40000, 'label' => '25K-40K'],
            ['min' => 40000, 'max' => 60000, 'label' => '40K-60K'],
            ['min' => 60000, 'max' => 100000, 'label' => '60K-100K'],
            ['min' => 100000, 'max' => PHP_INT_MAX, 'label' => 'Above 100K'],
        ];

        return collect($bands)->map(function ($band) use ($employees) {
            $count = $employees->filter(function ($salary) use ($band) {
                return $salary >= $band['min'] && $salary < $band['max'];
            })->count();

            return [
                'band' => $band['label'],
                'count' => $count,
                'min' => $band['min'],
                'max' => $band['max'] === PHP_INT_MAX ? null : $band['max'],
            ];
        })->toArray();
    }

    /**
     * Get payroll expense trend by period.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{period: string, expense: float, headcount: int}>
     */
    public function getPayrollExpenseTrend(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = PayrollEntry::query()
            ->join('payroll_periods', 'payroll_entries.payroll_period_id', '=', 'payroll_periods.id')
            ->whereBetween('payroll_periods.cutoff_end', [$range['start'], $range['end']])
            ->select([
                'payroll_periods.name as period',
                'payroll_periods.cutoff_end',
                DB::raw('SUM(gross_pay) as expense'),
                DB::raw('COUNT(DISTINCT employee_id) as headcount'),
            ])
            ->groupBy('payroll_periods.id', 'payroll_periods.name', 'payroll_periods.cutoff_end')
            ->orderBy('payroll_periods.cutoff_end');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        return $query->get()->map(function ($row) {
            return [
                'period' => $row->period,
                'expense' => (float) $row->expense,
                'headcount' => (int) $row->headcount,
            ];
        })->toArray();
    }

    // =========================================================================
    // RECRUITMENT METRICS
    // =========================================================================

    /**
     * Get recruitment metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     openPositions: int,
     *     totalApplications: int,
     *     hiredCount: int,
     *     rejectedCount: int,
     *     avgTimeToHire: float|null,
     *     offerAcceptanceRate: float
     * }
     */
    public function getRecruitmentMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        // Open positions (job postings that are active)
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

        // Applications in date range
        $appQuery = JobApplication::query()
            ->whereBetween('applied_at', [$range['start'], $range['end']]);

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $appQuery->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $totalApplications = (clone $appQuery)->count();
        $hiredCount = (clone $appQuery)->where('status', ApplicationStatus::Hired)->count();
        $rejectedCount = (clone $appQuery)->where('status', ApplicationStatus::Rejected)->count();

        // Average time to hire (days from applied_at to hired_at)
        $hiredApps = (clone $appQuery)
            ->where('status', ApplicationStatus::Hired)
            ->whereNotNull('hired_at')
            ->select(['applied_at', 'hired_at'])
            ->get();

        $avgTimeToHire = null;
        if ($hiredApps->isNotEmpty()) {
            $totalDays = $hiredApps->sum(function ($app) {
                return $app->applied_at->diffInDays($app->hired_at);
            });
            $avgTimeToHire = round($totalDays / $hiredApps->count(), 1);
        }

        // Offer acceptance rate (hired / (hired + rejected at offer stage))
        $offeredCount = (clone $appQuery)
            ->whereIn('status', [ApplicationStatus::Offer, ApplicationStatus::Hired, ApplicationStatus::Rejected])
            ->whereNotNull('offer_at')
            ->count();

        $offerAcceptanceRate = $offeredCount > 0
            ? round(($hiredCount / $offeredCount) * 100, 1)
            : 0;

        return [
            'openPositions' => $openPositions,
            'totalApplications' => $totalApplications,
            'hiredCount' => $hiredCount,
            'rejectedCount' => $rejectedCount,
            'avgTimeToHire' => $avgTimeToHire,
            'offerAcceptanceRate' => $offerAcceptanceRate,
        ];
    }

    /**
     * Get recruitment pipeline breakdown by status.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{stage: string, count: int, label: string}>
     */
    public function getRecruitmentPipeline(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $query = JobApplication::query()
            ->whereBetween('applied_at', [$range['start'], $range['end']])
            ->select([
                'status',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('status');

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('jobPosting', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $results = $query->get()->keyBy('status');

        // Build pipeline in order
        $stages = [
            ApplicationStatus::Applied,
            ApplicationStatus::Screening,
            ApplicationStatus::Interview,
            ApplicationStatus::Assessment,
            ApplicationStatus::Offer,
            ApplicationStatus::Hired,
        ];

        return collect($stages)->map(function ($status) use ($results) {
            $count = $results->get($status->value)?->count ?? 0;

            return [
                'stage' => $status->value,
                'count' => (int) $count,
                'label' => $status->label(),
            ];
        })->toArray();
    }

    // =========================================================================
    // PERFORMANCE METRICS
    // =========================================================================

    /**
     * Get performance metrics.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{
     *     totalParticipants: int,
     *     completedEvaluations: int,
     *     completionRate: float,
     *     averageRating: float|null,
     *     acknowledgedCount: int
     * }
     */
    public function getPerformanceMetrics(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        // Get participants with cycle instances in date range
        $query = PerformanceCycleParticipant::query()
            ->whereHas('performanceCycleInstance', function ($q) use ($range) {
                $q->whereBetween('end_date', [$range['start'], $range['end']]);
            });

        if ($departmentIds !== null && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $totalParticipants = (clone $query)->count();

        // Completed evaluations (has evaluation summary with final rating)
        $completedQuery = (clone $query)
            ->whereHas('evaluationSummary', function ($q) {
                $q->whereNotNull('final_rating');
            });

        $completedEvaluations = $completedQuery->count();

        $completionRate = $totalParticipants > 0
            ? round(($completedEvaluations / $totalParticipants) * 100, 1)
            : 0;

        // Average final overall score
        $summaries = EvaluationSummary::query()
            ->whereIn('performance_cycle_participant_id', (clone $query)->pluck('id'))
            ->whereNotNull('final_overall_score')
            ->pluck('final_overall_score');

        $averageRating = $summaries->isNotEmpty()
            ? round($summaries->avg(), 2)
            : null;

        // Acknowledged count
        $acknowledgedCount = EvaluationSummary::query()
            ->whereIn('performance_cycle_participant_id', (clone $query)->pluck('id'))
            ->whereNotNull('employee_acknowledged_at')
            ->count();

        return [
            'totalParticipants' => $totalParticipants,
            'completedEvaluations' => $completedEvaluations,
            'completionRate' => $completionRate,
            'averageRating' => $averageRating,
            'acknowledgedCount' => $acknowledgedCount,
        ];
    }

    /**
     * Get performance rating distribution.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<array{rating: string, count: int, label: string}>
     */
    public function getRatingDistribution(
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $departmentIds = null
    ): array {
        $range = $this->getDateRange($startDate, $endDate);

        $participantIds = PerformanceCycleParticipant::query()
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

        $ratings = [
            'exceptional' => 'Exceptional',
            'exceeds_expectations' => 'Exceeds Expectations',
            'meets_expectations' => 'Meets Expectations',
            'needs_improvement' => 'Needs Improvement',
            'unsatisfactory' => 'Unsatisfactory',
        ];

        return collect($ratings)->map(function ($label, $rating) use ($results) {
            return [
                'rating' => $rating,
                'count' => (int) ($results->get($rating)?->count ?? 0),
                'label' => $label,
            ];
        })->values()->toArray();
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
