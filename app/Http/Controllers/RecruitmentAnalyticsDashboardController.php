<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Services\RecruitmentAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentAnalyticsDashboardController extends Controller
{
    public function __construct(
        protected RecruitmentAnalyticsService $analyticsService
    ) {}

    /**
     * Display the Recruitment Analytics Dashboard.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-view-hr-analytics');

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $departmentIds = $this->getDepartmentIds($request);

        // Get all departments for filter dropdown
        $departments = $this->analyticsService->getDepartments();

        // Summary metrics (fast - load immediately)
        $summary = $this->analyticsService->getSummaryMetrics($startDate, $endDate, $departmentIds);

        return Inertia::render('Recruitment/AnalyticsDashboard', [
            // Filters
            'filters' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'departmentIds' => $departmentIds,
            ],
            'departments' => $departments,

            // Immediate data
            'summary' => $summary,

            // Deferred props for heavier metrics - Funnel
            'funnelMetrics' => Inertia::defer(fn () => $this->analyticsService->getFunnelMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'dropoutAnalysis' => Inertia::defer(fn () => $this->analyticsService->getDropoutAnalysis(
                $startDate,
                $endDate,
                $departmentIds
            )),

            // Time-to-Fill
            'timeToFillMetrics' => Inertia::defer(fn () => $this->analyticsService->getTimeToFillMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'timeToFillTrend' => Inertia::defer(fn () => $this->analyticsService->getTimeToFillTrend(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'timeToFillByDepartment' => Inertia::defer(fn () => $this->analyticsService->getTimeToFillByDepartment(
                $startDate,
                $endDate,
                $departmentIds
            )),

            // Source Effectiveness
            'sourceEffectiveness' => Inertia::defer(fn () => $this->analyticsService->getSourceEffectiveness(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'sourceQualityMetrics' => Inertia::defer(fn () => $this->analyticsService->getSourceQualityMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),

            // Offer Analytics
            'offerMetrics' => Inertia::defer(fn () => $this->analyticsService->getOfferMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'offerAcceptanceTrend' => Inertia::defer(fn () => $this->analyticsService->getOfferAcceptanceTrend(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'offerDeclineReasons' => Inertia::defer(fn () => $this->analyticsService->getOfferDeclineReasons(
                $startDate,
                $endDate,
                $departmentIds
            )),

            // Requisition Analytics
            'requisitionMetrics' => Inertia::defer(fn () => $this->analyticsService->getRequisitionMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'requisitionsByUrgency' => Inertia::defer(fn () => $this->analyticsService->getRequisitionsByUrgency(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'headcountVsHires' => Inertia::defer(fn () => $this->analyticsService->getHeadcountVsHires(
                $startDate,
                $endDate,
                $departmentIds
            )),

            // Interviewer Performance
            'interviewMetrics' => Inertia::defer(fn () => $this->analyticsService->getInterviewMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'interviewerLeaderboard' => Inertia::defer(fn () => $this->analyticsService->getInterviewerLeaderboard(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'interviewSchedulingMetrics' => Inertia::defer(fn () => $this->analyticsService->getInterviewSchedulingMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),

            // Hiring Trends
            'hiringVelocityTrend' => Inertia::defer(fn () => $this->analyticsService->getHiringVelocityTrend(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'seasonalPatterns' => Inertia::defer(fn () => $this->analyticsService->getSeasonalPatterns(
                $departmentIds
            )),
        ]);
    }

    /**
     * Get department IDs based on user role.
     *
     * @return array<int>|null
     */
    protected function getDepartmentIds(Request $request): ?array
    {
        $user = $request->user() ?? auth()->user();
        $tenant = tenant();

        if ($tenant === null || $user === null) {
            return null;
        }

        $role = $user->getRoleInTenant($tenant);

        // Supervisors can only see their department
        if ($role === TenantUserRole::Supervisor) {
            $employee = Employee::where('user_id', $user->id)->first();

            if ($employee && $employee->department_id) {
                return [$employee->department_id];
            }

            // No employee record or department - return empty to show no data
            return [];
        }

        // Admin/HR roles can filter by selected departments
        $selectedDepartments = $request->query('department_ids');

        if ($selectedDepartments) {
            if (is_string($selectedDepartments)) {
                return array_map('intval', explode(',', $selectedDepartments));
            }

            if (is_array($selectedDepartments)) {
                return array_map('intval', $selectedDepartments);
            }
        }

        // No filter - show all departments
        return null;
    }
}
