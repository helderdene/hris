<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PhilhealthReportType;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\Reports\PhilhealthReportService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page controller for PhilHealth compliance reports.
 *
 * Renders the PhilHealth reports dashboard page.
 */
class PhilhealthReportPageController extends Controller
{
    public function __construct(protected PhilhealthReportService $reportService) {}

    /**
     * Display the PhilHealth reports management page.
     */
    public function index(): Response
    {
        Gate::authorize('can-manage-organization');

        $periods = $this->reportService->getAvailablePeriods();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Reports/Philhealth/Index', [
            'reportTypes' => PhilhealthReportType::options(),
            'departments' => $departments,
            'years' => $periods['years'],
            'months' => $periods['months'],
        ]);
    }
}
