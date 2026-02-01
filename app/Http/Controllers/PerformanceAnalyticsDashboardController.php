<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Services\PerformanceAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceAnalyticsDashboardController extends Controller
{
    public function __construct(
        protected PerformanceAnalyticsService $analyticsService
    ) {}

    /**
     * Display the Performance Analytics Dashboard.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('can-view-performance-analytics');

        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : null;
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : null;
        $departmentIds = $this->getDepartmentIds($request);

        // Get all departments for filter dropdown
        $departments = Department::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Summary metrics (fast - load immediately)
        $summary = $this->analyticsService->getSummaryMetrics($departmentIds, $startDate, $endDate);

        return Inertia::render('Performance/AnalyticsDashboard', [
            // Filters
            'filters' => [
                'startDate' => $startDate?->toDateString(),
                'endDate' => $endDate?->toDateString(),
                'departmentIds' => $departmentIds,
            ],
            'departments' => $departments,

            // Immediate data
            'summary' => $summary,

            // Deferred props for heavier metrics
            'evaluationCompletion' => Inertia::defer(fn () => $this->analyticsService->getEvaluationCompletionMetrics(
                $departmentIds,
                $startDate,
                $endDate
            )),
            'ratingDistribution' => Inertia::defer(fn () => $this->analyticsService->getRatingDistribution(
                $departmentIds,
                $startDate,
                $endDate
            )),
            'ratingTrends' => Inertia::defer(fn () => $this->analyticsService->getRatingTrends(
                $departmentIds,
                $startDate,
                $endDate
            )),
            'developmentPlans' => Inertia::defer(fn () => $this->analyticsService->getDevelopmentPlanMetrics(
                $departmentIds,
                $startDate,
                $endDate
            )),
            'goalAchievement' => Inertia::defer(fn () => $this->analyticsService->getGoalAchievementMetrics(
                $departmentIds,
                $startDate,
                $endDate
            )),
            'kpiAchievement' => Inertia::defer(fn () => $this->analyticsService->getKpiAchievementMetrics(
                $departmentIds,
                $startDate,
                $endDate
            )),
            'byDepartment' => Inertia::defer(fn () => $this->analyticsService->getMetricsByDepartment(
                $departmentIds,
                $startDate,
                $endDate
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

        // Employees can only see their department
        if ($role === TenantUserRole::Employee) {
            $employee = Employee::where('user_id', $user->id)->first();

            if ($employee && $employee->department_id) {
                return [$employee->department_id];
            }

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
