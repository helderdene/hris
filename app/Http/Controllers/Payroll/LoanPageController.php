<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeLoanListResource;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoanPageController extends Controller
{
    /**
     * Display the loans management page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = EmployeeLoan::query()
            ->with(['employee.department', 'employee.position'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('loan_type')) {
            $query->where('loan_type', $request->input('loan_type'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('category')) {
            $category = $request->input('category');
            if ($category === 'government') {
                $query->governmentLoans();
            } elseif ($category === 'company') {
                $query->companyLoans();
            }
        }

        $loans = $query->paginate(25);

        $employees = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix']);

        $summary = [
            'total_loans' => EmployeeLoan::count(),
            'active_loans' => EmployeeLoan::active()->count(),
            'total_outstanding' => (float) EmployeeLoan::active()->sum('remaining_balance'),
            'total_monthly_deductions' => (float) EmployeeLoan::active()->sum('monthly_deduction'),
        ];

        return Inertia::render('Payroll/Loans/Index', [
            'loans' => EmployeeLoanListResource::collection($loans),
            'employees' => $employees->map(fn ($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'full_name' => $emp->full_name,
            ]),
            'loanTypes' => LoanType::groupedOptions(),
            'loanStatuses' => LoanStatus::options(),
            'filters' => [
                'status' => $request->input('status'),
                'loan_type' => $request->input('loan_type'),
                'employee_id' => $request->input('employee_id') ? (int) $request->input('employee_id') : null,
                'category' => $request->input('category'),
            ],
            'summary' => $summary,
        ]);
    }
}
