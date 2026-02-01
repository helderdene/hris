<?php

namespace App\Http\Controllers\My;

use App\Enums\LoanApplicationStatus;
use App\Enums\LoanType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyLoanApplicationController extends Controller
{
    /**
     * Display the employee's loan applications.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        $applications = [];

        if ($employee) {
            $query = LoanApplication::query()
                ->forEmployee($employee)
                ->with('reviewer')
                ->orderByDesc('created_at');

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $applications = $query->get()->map(fn (LoanApplication $app) => [
                'id' => $app->id,
                'reference_number' => $app->reference_number,
                'loan_type' => $app->loan_type->value,
                'loan_type_label' => $app->loan_type->label(),
                'loan_type_category' => $app->loan_type->category(),
                'amount_requested' => (float) $app->amount_requested,
                'term_months' => $app->term_months,
                'purpose' => $app->purpose,
                'status' => $app->status->value,
                'status_label' => $app->status->label(),
                'status_color' => $app->status->color(),
                'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
                'reviewed_at' => $app->reviewed_at?->format('Y-m-d H:i:s'),
                'created_at' => $app->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $app->can_be_edited,
                'can_be_cancelled' => $app->can_be_cancelled,
            ]);
        }

        return Inertia::render('My/LoanApplications/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'applications' => $applications,
            'loanTypes' => LoanType::groupedOptions(),
            'statuses' => LoanApplicationStatus::options(),
            'filters' => [
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Display the create loan application form.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        return Inertia::render('My/LoanApplications/Create', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'loanTypes' => LoanType::groupedOptions(),
        ]);
    }

    /**
     * Display a specific loan application.
     */
    public function show(Request $request, string $tenant, LoanApplication $loanApplication): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        // Ensure employee can only view their own applications
        abort_unless($employee && $loanApplication->employee_id === $employee->id, 403);

        $loanApplication->load(['employee', 'reviewer', 'employeeLoan']);

        return Inertia::render('My/LoanApplications/Show', [
            'application' => [
                'id' => $loanApplication->id,
                'reference_number' => $loanApplication->reference_number,
                'loan_type' => $loanApplication->loan_type->value,
                'loan_type_label' => $loanApplication->loan_type->label(),
                'loan_type_category' => $loanApplication->loan_type->category(),
                'amount_requested' => (float) $loanApplication->amount_requested,
                'term_months' => $loanApplication->term_months,
                'purpose' => $loanApplication->purpose,
                'documents' => $loanApplication->documents,
                'status' => $loanApplication->status->value,
                'status_label' => $loanApplication->status->label(),
                'status_color' => $loanApplication->status->color(),
                'reviewer' => $loanApplication->reviewer ? [
                    'full_name' => $loanApplication->reviewer->full_name,
                ] : null,
                'reviewer_remarks' => $loanApplication->reviewer_remarks,
                'reviewed_at' => $loanApplication->reviewed_at?->format('Y-m-d H:i:s'),
                'employee_loan' => $loanApplication->employeeLoan ? [
                    'id' => $loanApplication->employeeLoan->id,
                    'total_amount' => (float) $loanApplication->employeeLoan->total_amount,
                    'monthly_deduction' => (float) $loanApplication->employeeLoan->monthly_deduction,
                    'interest_rate' => (float) $loanApplication->employeeLoan->interest_rate,
                    'start_date' => $loanApplication->employeeLoan->start_date?->format('M d, Y'),
                ] : null,
                'cancellation_reason' => $loanApplication->cancellation_reason,
                'submitted_at' => $loanApplication->submitted_at?->format('Y-m-d H:i:s'),
                'created_at' => $loanApplication->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $loanApplication->can_be_edited,
                'can_be_cancelled' => $loanApplication->can_be_cancelled,
            ],
        ]);
    }
}
