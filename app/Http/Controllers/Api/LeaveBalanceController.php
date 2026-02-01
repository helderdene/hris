<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveBalanceAdjustmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustLeaveBalanceRequest;
use App\Http\Requests\InitializeBalancesRequest;
use App\Http\Requests\ProcessYearEndRequest;
use App\Http\Resources\LeaveBalanceResource;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API Controller for leave balance management.
 */
class LeaveBalanceController extends Controller
{
    public function __construct(
        protected LeaveBalanceService $leaveBalanceService
    ) {}

    /**
     * List leave balances with filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LeaveBalance::query()
            ->with(['employee.department', 'employee.position', 'leaveType']);

        // Filter by year
        if ($request->filled('year')) {
            $query->forYear((int) $request->input('year'));
        } else {
            $query->forYear(now()->year);
        }

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->forEmployee((int) $request->input('employee_id'));
        }

        // Filter by leave type
        if ($request->filled('leave_type_id')) {
            $query->forLeaveType((int) $request->input('leave_type_id'));
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'employee_id');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Paginate
        $perPage = min((int) $request->input('per_page', 25), 100);
        $balances = $query->paginate($perPage);

        return LeaveBalanceResource::collection($balances);
    }

    /**
     * Get aggregated summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);

        $query = LeaveBalance::query()->forYear($year);

        if ($request->filled('leave_type_id')) {
            $query->forLeaveType((int) $request->input('leave_type_id'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        $balances = $query->get();

        $totalEmployees = $balances->pluck('employee_id')->unique()->count();
        $totalCredits = $balances->sum(fn ($b) => $b->total_credits);
        $totalUsed = $balances->sum('used');
        $totalPending = $balances->sum('pending');
        $totalAvailable = $balances->sum(fn ($b) => $b->available);
        $totalExpired = $balances->sum('expired');

        // Summary by leave type
        $byLeaveType = $balances->groupBy('leave_type_id')
            ->map(function ($group) {
                $leaveType = $group->first()?->leaveType;

                return [
                    'leave_type_id' => $group->first()?->leave_type_id,
                    'leave_type_name' => $leaveType?->name ?? 'Unknown',
                    'total_employees' => $group->pluck('employee_id')->unique()->count(),
                    'total_credits' => $group->sum(fn ($b) => $b->total_credits),
                    'total_used' => $group->sum('used'),
                    'total_available' => $group->sum(fn ($b) => $b->available),
                ];
            })
            ->values();

        return response()->json([
            'year' => $year,
            'total_employees' => $totalEmployees,
            'total_credits' => round($totalCredits, 2),
            'total_used' => round($totalUsed, 2),
            'total_pending' => round($totalPending, 2),
            'total_available' => round($totalAvailable, 2),
            'total_expired' => round($totalExpired, 2),
            'utilization_rate' => $totalCredits > 0
                ? round(($totalUsed / $totalCredits) * 100, 1)
                : 0,
            'by_leave_type' => $byLeaveType,
        ]);
    }

    /**
     * Show a single leave balance with history.
     */
    public function show(LeaveBalance $balance): LeaveBalanceResource
    {
        $balance->load([
            'employee.department',
            'employee.position',
            'leaveType',
            'adjustmentHistory.adjustedByUser',
        ]);

        return new LeaveBalanceResource($balance);
    }

    /**
     * Manually adjust a leave balance.
     */
    public function adjust(AdjustLeaveBalanceRequest $request, LeaveBalance $balance): JsonResponse
    {
        $validated = $request->validated();

        $adjustment = $this->leaveBalanceService->recordAdjustment(
            balance: $balance,
            type: LeaveBalanceAdjustmentType::from($validated['adjustment_type']),
            days: (float) $validated['days'],
            reason: $validated['reason'],
            userId: $request->user()->id
        );

        $balance->refresh();

        return response()->json([
            'message' => 'Balance adjusted successfully.',
            'balance' => new LeaveBalanceResource($balance->load('leaveType')),
            'adjustment' => [
                'id' => $adjustment->id,
                'type' => $adjustment->adjustment_type?->label(),
                'days' => $adjustment->days,
                'new_balance' => $adjustment->new_balance,
            ],
        ]);
    }

    /**
     * Initialize balances for employees.
     */
    public function initialize(InitializeBalancesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $year = (int) $validated['year'];
        $employeeId = $validated['employee_id'] ?? null;

        if ($employeeId !== null) {
            // Initialize for single employee
            $employee = Employee::findOrFail($employeeId);
            $balances = $this->leaveBalanceService->initializeBalancesForEmployee($employee, $year);

            return response()->json([
                'message' => "Initialized {$balances->count()} balances for {$employee->full_name}.",
                'count' => $balances->count(),
            ]);
        }

        // Initialize for all active employees
        $employees = Employee::query()->active()->get();
        $totalInitialized = 0;

        foreach ($employees as $employee) {
            $balances = $this->leaveBalanceService->initializeBalancesForEmployee($employee, $year);
            $totalInitialized += $balances->count();
        }

        return response()->json([
            'message' => "Initialized {$totalInitialized} balances for {$employees->count()} employees.",
            'employees_count' => $employees->count(),
            'balances_count' => $totalInitialized,
        ]);
    }

    /**
     * Process year-end carry-over and forfeiture.
     */
    public function processYearEnd(ProcessYearEndRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $year = (int) $validated['year'];

        $result = $this->leaveBalanceService->processYearEnd($year);

        return response()->json([
            'message' => "Year-end processing completed for {$year}.",
            'year' => $year,
            'carried_over' => $result['carried_over'],
            'forfeited' => $result['forfeited'],
            'initialized' => $result['initialized'],
        ]);
    }

    /**
     * Get leave balances for a specific employee.
     */
    public function employeeBalances(Request $request, Employee $employee): AnonymousResourceCollection
    {
        $year = $request->input('year', now()->year);

        $balances = LeaveBalance::query()
            ->forEmployee($employee)
            ->forYear($year)
            ->with(['leaveType', 'adjustmentHistory.adjustedByUser'])
            ->get();

        return LeaveBalanceResource::collection($balances);
    }

    /**
     * Get available years for filters.
     */
    public function availableYears(): JsonResponse
    {
        $years = LeaveBalance::query()
            ->selectRaw('DISTINCT year')
            ->orderBy('year', 'desc')
            ->pluck('year');

        // Always include current year
        $currentYear = now()->year;
        if (! $years->contains($currentYear)) {
            $years->prepend($currentYear);
        }

        return response()->json([
            'years' => $years->sort()->reverse()->values(),
        ]);
    }

    /**
     * Get available leave types for filters.
     */
    public function leaveTypes(): JsonResponse
    {
        $leaveTypes = LeaveType::query()
            ->active()
            ->select('id', 'name', 'code', 'leave_category')
            ->orderBy('name')
            ->get()
            ->map(fn ($lt) => [
                'id' => $lt->id,
                'name' => $lt->name,
                'code' => $lt->code,
                'category' => $lt->leave_category?->value,
                'category_label' => $lt->leave_category?->label(),
            ]);

        return response()->json([
            'leave_types' => $leaveTypes,
        ]);
    }
}
