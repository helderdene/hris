<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PagibigReportType;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PayrollPeriod;
use App\Services\Reports\PagibigReportService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page controller for Pag-IBIG compliance reports.
 *
 * Renders the Pag-IBIG reports dashboard page.
 */
class PagibigReportPageController extends Controller
{
    public function __construct(protected PagibigReportService $reportService) {}

    /**
     * Display the Pag-IBIG reports management page.
     */
    public function index(): Response
    {
        Gate::authorize('can-manage-organization');

        $periods = $this->reportService->getAvailablePeriods();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $payrollPeriods = PayrollPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('cutoff_start')
            ->limit(24)
            ->get(['id', 'name', 'year', 'cutoff_start', 'cutoff_end']);

        return Inertia::render('Reports/Pagibig/Index', [
            'reportTypes' => PagibigReportType::options(),
            'departments' => $departments,
            'years' => $periods['years'],
            'months' => $periods['months'],
            'payrollPeriods' => $payrollPeriods->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'year' => $p->year,
                'month' => $p->cutoff_start->format('n'),
                'label' => $p->cutoff_start->format('M Y').' - '.$p->name,
            ]),
        ]);
    }
}
