<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Services\HRAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HRAnalyticsDashboardController extends Controller
{
    public function __construct(
        protected HRAnalyticsService $analyticsService
    ) {}

    /**
     * Display the HR Analytics Dashboard.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-view-hr-analytics');

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $departmentIds = $this->getDepartmentIds($request);

        // Get all departments for filter dropdown
        $departments = $this->analyticsService->getDepartments();

        // Headcount metrics (fast - load immediately)
        $headcount = $this->analyticsService->getHeadcountMetrics($departmentIds);

        return Inertia::render('Hr/AnalyticsDashboard', [
            // Filters
            'filters' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'departmentIds' => $departmentIds,
            ],
            'departments' => $departments,

            // Immediate data
            'headcount' => $headcount,

            // Deferred props for heavier metrics
            'attendance' => Inertia::defer(fn () => $this->analyticsService->getAttendanceMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'attendanceTrend' => Inertia::defer(fn () => $this->analyticsService->getAttendanceTrend(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'attendanceByDepartment' => Inertia::defer(fn () => $this->analyticsService->getAttendanceByDepartment(
                $startDate,
                $endDate,
                $departmentIds
            )),

            'leave' => Inertia::defer(fn () => $this->analyticsService->getLeaveMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'leaveTypeBreakdown' => Inertia::defer(fn () => $this->analyticsService->getLeaveTypeBreakdown(
                $startDate,
                $endDate,
                $departmentIds
            )),

            'compensation' => Inertia::defer(fn () => $this->analyticsService->getCompensationMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'salaryDistribution' => Inertia::defer(fn () => $this->analyticsService->getSalaryDistribution(
                $departmentIds
            )),
            'payrollTrend' => Inertia::defer(fn () => $this->analyticsService->getPayrollExpenseTrend(
                $startDate,
                $endDate,
                $departmentIds
            )),

            'recruitment' => Inertia::defer(fn () => $this->analyticsService->getRecruitmentMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'recruitmentPipeline' => Inertia::defer(fn () => $this->analyticsService->getRecruitmentPipeline(
                $startDate,
                $endDate,
                $departmentIds
            )),

            'performance' => Inertia::defer(fn () => $this->analyticsService->getPerformanceMetrics(
                $startDate,
                $endDate,
                $departmentIds
            )),
            'ratingDistribution' => Inertia::defer(fn () => $this->analyticsService->getRatingDistribution(
                $startDate,
                $endDate,
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
