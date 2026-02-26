<?php

namespace App\Services;

use App\Enums\DtrStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\Employee;
use Carbon\Carbon;

class EmployeeSummaryService
{
    public function __construct(private Employee $employee) {}

    /**
     * Get attendance recap for the given period.
     *
     * @return array{days_present: int, days_absent: int, days_on_leave: int, total_late_minutes: int, total_late_formatted: string}
     */
    public function getAttendanceRecap(string $period): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);

        $dtrs = $this->employee->dailyTimeRecords()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $daysPresent = $dtrs->where('status', DtrStatus::Present)->count();
        $daysAbsent = $dtrs->where('status', DtrStatus::Absent)->count();
        $totalLateMinutes = (int) $dtrs->sum('late_minutes');

        // Count approved leave days that overlap with the period
        $daysOnLeave = $this->employee->leaveApplications()
            ->where('status', LeaveApplicationStatus::Approved)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->sum('total_days');

        $hours = intdiv($totalLateMinutes, 60);
        $minutes = $totalLateMinutes % 60;

        return [
            'days_present' => $daysPresent,
            'days_absent' => $daysAbsent,
            'days_on_leave' => (int) $daysOnLeave,
            'total_late_minutes' => $totalLateMinutes,
            'total_late_formatted' => sprintf('%dh %dm', $hours, $minutes),
        ];
    }

    /**
     * Get overtime summary for the given period.
     *
     * @return array{approved_hours: float, approved_hours_formatted: string, request_count: int, total_overtime_minutes: int}
     */
    public function getOvertimeSummary(string $period): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);

        // Approved overtime requests in period
        $approvedRequests = $this->employee->overtimeRequests()
            ->where('status', OvertimeRequestStatus::Approved)
            ->whereBetween('overtime_date', [$startDate, $endDate])
            ->get();

        $requestCount = $approvedRequests->count();
        $totalExpectedMinutes = (int) $approvedRequests->sum('expected_minutes');

        // Actual overtime from DTR where overtime is approved
        $actualOvertimeMinutes = (int) $this->employee->dailyTimeRecords()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('overtime_approved', true)
            ->sum('overtime_minutes');

        $approvedHours = round($totalExpectedMinutes / 60, 1);
        $hours = intdiv($totalExpectedMinutes, 60);
        $minutes = $totalExpectedMinutes % 60;

        return [
            'approved_hours' => $approvedHours,
            'approved_hours_formatted' => sprintf('%dh %dm', $hours, $minutes),
            'request_count' => $requestCount,
            'total_overtime_minutes' => $actualOvertimeMinutes,
        ];
    }

    /**
     * Get leave balances for the current year.
     *
     * @return array<int, array{leave_type: string, total_credits: float, used: float, pending: float, available: float}>
     */
    public function getLeaveBalances(): array
    {
        $currentYear = now()->year;

        return $this->employee->leaveBalances()
            ->with('leaveType')
            ->where('year', $currentYear)
            ->get()
            ->map(fn ($balance) => [
                'leave_type' => $balance->leaveType->name ?? 'Unknown',
                'total_credits' => (float) $balance->total_credits,
                'used' => (float) $balance->used,
                'pending' => (float) $balance->pending,
                'available' => (float) $balance->available,
            ])
            ->values()
            ->all();
    }

    /**
     * Get performance summary from the latest performance cycle participation.
     *
     * @return array{final_overall_score: float|null, final_rating: string|null, final_rating_label: string|null, kpi_achievement: float|null, goal_progress: float|null, cycle_name: string|null}|null
     */
    public function getPerformanceSummary(): ?array
    {
        $latestParticipant = $this->employee->performanceCycleParticipants()
            ->with([
                'evaluationSummary',
                'performanceCycleInstance.performanceCycle',
            ])
            ->whereHas('evaluationSummary')
            ->latest('id')
            ->first();

        if (! $latestParticipant) {
            return null;
        }

        $summary = $latestParticipant->evaluationSummary;
        $kpiSummary = $latestParticipant->kpiSummary();
        $goalSummary = $latestParticipant->goalSummary();

        return [
            'final_overall_score' => $summary->final_overall_score ? (float) $summary->final_overall_score : null,
            'final_rating' => $summary->final_rating,
            'final_rating_label' => $summary->getFinalRatingLabel(),
            'kpi_achievement' => $kpiSummary['weighted_achievement'] ?? null,
            'goal_progress' => $goalSummary['average_progress'] ?? null,
            'cycle_name' => $latestParticipant->performanceCycleInstance?->name,
        ];
    }

    /**
     * Get performance growth data across all performance cycles.
     *
     * @return array<int, array{cycle_name: string, overall_score: float|null, kpi_achievement: float|null}>
     */
    public function getPerformanceGrowth(): array
    {
        return $this->employee->performanceCycleParticipants()
            ->with([
                'evaluationSummary',
                'performanceCycleInstance.performanceCycle',
            ])
            ->whereHas('evaluationSummary')
            ->join('performance_cycle_instances', 'performance_cycle_participants.performance_cycle_instance_id', '=', 'performance_cycle_instances.id')
            ->orderBy('performance_cycle_instances.start_date')
            ->select('performance_cycle_participants.*')
            ->get()
            ->map(fn ($participant) => [
                'cycle_name' => $participant->performanceCycleInstance?->name ?? 'Unknown',
                'overall_score' => $participant->evaluationSummary?->final_overall_score
                    ? (float) $participant->evaluationSummary->final_overall_score
                    : null,
                'kpi_achievement' => $participant->evaluationSummary?->kpi_achievement_score
                    ? (float) $participant->evaluationSummary->kpi_achievement_score
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Convert period string to a date range.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::today()],
            'this_week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };
    }
}
