<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\AdjustmentCategory;
use App\Enums\AdjustmentFrequency;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\RecurringInterval;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeAdjustmentListResource;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdjustmentPageController extends Controller
{
    /**
     * Display the adjustments management page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = EmployeeAdjustment::query()
            ->with(['employee.department', 'employee.position'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('adjustment_category', $request->input('category'));
        }

        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->input('adjustment_type'));
        }

        if ($request->filled('frequency')) {
            $query->where('frequency', $request->input('frequency'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $adjustments = $query->paginate(25);

        $employees = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix']);

        $payrollPeriods = PayrollPeriod::query()
            ->where('year', '>=', now()->year)
            ->orderBy('cutoff_start', 'desc')
            ->get(['id', 'name', 'cutoff_start', 'cutoff_end', 'year']);

        $summary = [
            'total_adjustments' => EmployeeAdjustment::count(),
            'active_adjustments' => EmployeeAdjustment::active()->count(),
            'total_earnings' => (float) EmployeeAdjustment::active()
                ->earnings()
                ->sum('amount'),
            'total_deductions' => (float) EmployeeAdjustment::active()
                ->deductions()
                ->sum('amount'),
        ];

        return Inertia::render('Payroll/Adjustments/Index', [
            'adjustments' => EmployeeAdjustmentListResource::collection($adjustments),
            'employees' => $employees->map(fn ($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'full_name' => $emp->full_name,
            ]),
            'payrollPeriods' => $payrollPeriods->map(fn ($period) => [
                'id' => $period->id,
                'name' => $period->name,
                'cutoff_start' => $period->cutoff_start->format('Y-m-d'),
                'cutoff_end' => $period->cutoff_end->format('Y-m-d'),
                'year' => $period->year,
            ]),
            'adjustmentTypes' => AdjustmentType::groupedOptions(),
            'adjustmentCategories' => AdjustmentCategory::options(),
            'adjustmentStatuses' => AdjustmentStatus::options(),
            'adjustmentFrequencies' => AdjustmentFrequency::options(),
            'recurringIntervals' => RecurringInterval::options(),
            'filters' => [
                'status' => $request->input('status'),
                'category' => $request->input('category'),
                'adjustment_type' => $request->input('adjustment_type'),
                'frequency' => $request->input('frequency'),
                'employee_id' => $request->input('employee_id') ? (int) $request->input('employee_id') : null,
            ],
            'summary' => $summary,
        ]);
    }
}
