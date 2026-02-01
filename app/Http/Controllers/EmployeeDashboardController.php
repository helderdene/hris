<?php

namespace App\Http\Controllers;

use App\Services\EmployeeDashboardService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeDashboardController extends Controller
{
    public function __construct(
        protected EmployeeDashboardService $dashboardService
    ) {}

    /**
     * Display the employee dashboard with workforce metrics.
     */
    public function dashboard(): Response
    {
        Gate::authorize('can-manage-employees');

        return Inertia::render('Employees/Dashboard', [
            'headcount' => $this->dashboardService->getTotalHeadcount(),
            'newHires' => $this->dashboardService->getNewHiresMetrics(),
            'separations' => $this->dashboardService->getSeparationsMetrics(),
            'turnover' => $this->dashboardService->getTurnoverRate(),
            'tenureDistribution' => $this->dashboardService->getTenureDistribution(),
            'employmentTypeBreakdown' => $this->dashboardService->getEmploymentTypeBreakdown(),
            'departmentHeadcounts' => $this->dashboardService->getDepartmentHeadcounts(),
        ]);
    }
}
