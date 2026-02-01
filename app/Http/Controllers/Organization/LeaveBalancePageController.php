<?php

namespace App\Http\Controllers\Organization;

use App\Enums\LeaveBalanceAdjustmentType;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for rendering the Leave Balance management page.
 */
class LeaveBalancePageController extends Controller
{
    /**
     * Display the leave balances management page.
     */
    public function index(Request $request): Response
    {
        $year = $request->input('year', now()->year);

        // Get available years
        $availableYears = LeaveBalance::query()
            ->selectRaw('DISTINCT year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // Always include current and next year
        $currentYear = now()->year;
        if (! in_array($currentYear, $availableYears)) {
            $availableYears[] = $currentYear;
        }
        if (! in_array($currentYear + 1, $availableYears)) {
            $availableYears[] = $currentYear + 1;
        }
        rsort($availableYears);

        // Get leave types
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

        // Get departments for filter
        $departments = Department::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get adjustment types for the form
        $adjustmentTypes = collect(LeaveBalanceAdjustmentType::cases())
            ->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]);

        // Get summary statistics
        $summary = $this->getSummary($year, $request->input('leave_type_id'), $request->input('department_id'));

        return Inertia::render('LeaveManagement/Balances/Index', [
            'filters' => [
                'year' => (int) $year,
                'leave_type_id' => $request->input('leave_type_id'),
                'department_id' => $request->input('department_id'),
            ],
            'availableYears' => $availableYears,
            'leaveTypes' => $leaveTypes,
            'departments' => $departments,
            'adjustmentTypes' => $adjustmentTypes,
            'summary' => $summary,
        ]);
    }

    /**
     * Get summary statistics for the specified filters.
     *
     * @return array<string, mixed>
     */
    protected function getSummary(int $year, ?int $leaveTypeId, ?int $departmentId): array
    {
        $query = LeaveBalance::query()->forYear($year);

        if ($leaveTypeId !== null) {
            $query->forLeaveType($leaveTypeId);
        }

        if ($departmentId !== null) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $balances = $query->get();

        $totalEmployees = $balances->pluck('employee_id')->unique()->count();
        $totalCredits = $balances->sum(fn ($b) => $b->total_credits);
        $totalUsed = $balances->sum('used');
        $totalPending = $balances->sum('pending');
        $totalAvailable = $balances->sum(fn ($b) => $b->available);

        return [
            'total_employees' => $totalEmployees,
            'total_credits' => round($totalCredits, 2),
            'total_used' => round($totalUsed, 2),
            'total_pending' => round($totalPending, 2),
            'total_available' => round($totalAvailable, 2),
            'utilization_rate' => $totalCredits > 0
                ? round(($totalUsed / $totalCredits) * 100, 1)
                : 0,
        ];
    }
}
