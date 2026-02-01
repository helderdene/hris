<?php

namespace App\Http\Controllers;

use App\Enums\LoanApplicationStatus;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoanApprovalPageController extends Controller
{
    /**
     * Display the loan approvals index page for HR.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = LoanApplication::query()
            ->with(['employee.department', 'employee.position', 'reviewer'])
            ->orderByDesc('created_at');

        $status = $request->input('status', 'pending');
        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->get()->map(fn (LoanApplication $app) => [
            'id' => $app->id,
            'reference_number' => $app->reference_number,
            'employee' => [
                'id' => $app->employee->id,
                'full_name' => $app->employee->full_name,
                'employee_number' => $app->employee->employee_number,
                'department' => $app->employee->department?->name,
                'position' => $app->employee->position?->name,
            ],
            'loan_type' => $app->loan_type->value,
            'loan_type_label' => $app->loan_type->label(),
            'loan_type_category' => $app->loan_type->category(),
            'amount_requested' => (float) $app->amount_requested,
            'term_months' => $app->term_months,
            'purpose' => $app->purpose,
            'documents' => $app->documents,
            'status' => $app->status->value,
            'status_label' => $app->status->label(),
            'status_color' => $app->status->color(),
            'reviewer' => $app->reviewer ? [
                'full_name' => $app->reviewer->full_name,
            ] : null,
            'reviewer_remarks' => $app->reviewer_remarks,
            'reviewed_at' => $app->reviewed_at?->format('Y-m-d H:i:s'),
            'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
            'created_at' => $app->created_at->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('LoanApprovals/Index', [
            'applications' => $applications,
            'statuses' => LoanApplicationStatus::options(),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }
}
