<?php

namespace App\Http\Controllers;

use App\Http\Resources\DailyTimeRecordResource;
use App\Http\Resources\EmployeeListResource;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Dtr\DtrPeriodAggregator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DtrController extends Controller
{
    public function __construct(
        protected DtrPeriodAggregator $aggregator
    ) {}

    /**
     * Display the DTR list page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-employees');

        // Get filter parameters with defaults
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $query = DailyTimeRecord::query()
            ->with(['employee.department', 'employee.position', 'workSchedule'])
            ->orderBy('date', 'desc')
            ->orderBy('employee_id');

        // Filter by date range
        $query->whereBetween('date', [$dateFrom, $dateTo]);

        // Filter by department
        if ($departmentId = $request->input('department_id')) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        // Filter by employee
        if ($employeeId = $request->input('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by needs_review
        if ($request->has('needs_review') && $request->input('needs_review') !== '') {
            $query->where('needs_review', $request->boolean('needs_review'));
        }

        $records = $query->paginate(50)->withQueryString();

        // Get dropdown data for filters
        $departments = Department::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $employees = Employee::query()
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'employee_number'])
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]);

        // Get summary stats for the period
        $summaryQuery = DailyTimeRecord::query()
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($departmentId = $request->input('department_id')) {
            $summaryQuery->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $summaryRecords = $summaryQuery->get();
        $summary = $this->aggregator->aggregateRecords(
            $summaryRecords,
            Carbon::parse($dateFrom),
            Carbon::parse($dateTo)
        );

        return Inertia::render('TimeAttendance/Dtr/Index', [
            'records' => DailyTimeRecordResource::collection($records),
            'departments' => $departments,
            'employees' => $employees,
            'summary' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'department_id' => $request->input('department_id'),
                'employee_id' => $request->input('employee_id'),
                'status' => $request->input('status'),
                'needs_review' => $request->input('needs_review'),
            ],
        ]);
    }

    /**
     * Display DTR for a specific employee.
     */
    public function show(Request $request, string $tenant, Employee $employee): Response
    {
        Gate::authorize('can-manage-employees');

        // Get filter parameters with defaults
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $records = DailyTimeRecord::query()
            ->where('employee_id', $employee->id)
            ->with(['workSchedule', 'punches.attendanceLog'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'desc')
            ->get();

        // Get summary for the period
        $summary = $this->aggregator->getSummary(
            $employee,
            Carbon::parse($dateFrom),
            Carbon::parse($dateTo)
        );

        // Load employee with relationships
        $employee->load(['department', 'position', 'workLocation']);

        return Inertia::render('TimeAttendance/Dtr/Show', [
            'employee' => new EmployeeListResource($employee),
            'records' => [
                'data' => DailyTimeRecordResource::collection($records),
            ],
            'summary' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
