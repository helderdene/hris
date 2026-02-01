<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayrollEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollEntryListResource;
use App\Http\Resources\PayrollEntryResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\Department;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayrollEntryPageController extends Controller
{
    /**
     * Display the payroll entries list for a period.
     */
    public function index(Request $request, string $tenant, PayrollPeriod $period): Response
    {
        Gate::authorize('can-manage-organization');

        $query = PayrollEntry::query()
            ->with(['employee.department', 'employee.position'])
            ->where('payroll_period_id', $period->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $entries = $query
            ->orderBy('employee_name')
            ->paginate(25)
            ->withQueryString();

        $departments = Department::query()
            ->whereHas('employees.payrollEntries', fn ($q) => $q->where('payroll_period_id', $period->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $summary = [
            'total_employees' => PayrollEntry::where('payroll_period_id', $period->id)->count(),
            'total_gross' => PayrollEntry::where('payroll_period_id', $period->id)->sum('gross_pay'),
            'total_deductions' => PayrollEntry::where('payroll_period_id', $period->id)->sum('total_deductions'),
            'total_net' => PayrollEntry::where('payroll_period_id', $period->id)->sum('net_pay'),
            'by_status' => PayrollEntry::where('payroll_period_id', $period->id)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];

        return Inertia::render('Payroll/Entries/Index', [
            'period' => new PayrollPeriodResource($period->load('payrollCycle')),
            'entries' => PayrollEntryListResource::collection($entries),
            'departments' => $departments,
            'statusOptions' => $this->getStatusOptions(),
            'summary' => $summary,
            'filters' => [
                'status' => $request->input('status'),
                'department_id' => $request->input('department_id'),
                'search' => $request->input('search'),
            ],
        ]);
    }

    /**
     * Display a single payroll entry (payslip).
     */
    public function show(string $tenant, PayrollEntry $entry): Response
    {
        Gate::authorize('can-manage-organization');

        $entry->load([
            'payrollPeriod.payrollCycle',
            'employee.department',
            'employee.position',
            'employee.compensation',
            'earnings',
            'deductions',
        ]);

        return Inertia::render('Payroll/Entries/Show', [
            'entry' => new PayrollEntryResource($entry),
            'statusOptions' => $this->getStatusOptions(),
        ]);
    }

    /**
     * Get status options as array for frontend.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function getStatusOptions(): array
    {
        return array_map(
            fn (PayrollEntryStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->colorClass(),
            ],
            PayrollEntryStatus::cases()
        );
    }
}
