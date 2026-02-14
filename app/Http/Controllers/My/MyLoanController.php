<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyLoanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        $loans = [];

        if ($employee) {
            $loans = EmployeeLoan::query()
                ->forEmployee($employee->id)
                ->with('payments')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($loan) => [
                    'id' => $loan->id,
                    'loan_type' => $loan->loan_type->value,
                    'loan_type_label' => $loan->loan_type->label(),
                    'loan_type_category' => $loan->loan_type->category(),
                    'loan_code' => $loan->loan_code,
                    'reference_number' => $loan->reference_number,
                    'principal_amount' => (float) $loan->principal_amount,
                    'total_amount' => (float) $loan->total_amount,
                    'total_paid' => (float) $loan->total_paid,
                    'remaining_balance' => (float) $loan->remaining_balance,
                    'monthly_deduction' => (float) $loan->monthly_deduction,
                    'interest_rate' => (float) $loan->interest_rate,
                    'term_months' => $loan->term_months,
                    'status' => $loan->status->value,
                    'status_label' => $loan->status->label(),
                    'status_color' => $loan->status->color(),
                    'start_date' => $loan->start_date?->format('M d, Y'),
                    'expected_end_date' => $loan->expected_end_date?->format('M d, Y'),
                    'progress_percentage' => $loan->getProgressPercentage(),
                ]);
        }

        return Inertia::render('My/Loans/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'loans' => $loans,
        ]);
    }

    public function show(Request $request, EmployeeLoan $loan): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        // Ensure employee can only view their own loans
        abort_unless($employee && $loan->employee_id === $employee->id, 403);

        $loan->load('payments');

        return Inertia::render('My/Loans/Show', [
            'loan' => [
                'id' => $loan->id,
                'loan_type' => $loan->loan_type->value,
                'loan_type_label' => $loan->loan_type->label(),
                'loan_type_category' => $loan->loan_type->category(),
                'loan_code' => $loan->loan_code,
                'reference_number' => $loan->reference_number,
                'principal_amount' => (float) $loan->principal_amount,
                'interest_rate' => (float) $loan->interest_rate,
                'monthly_deduction' => (float) $loan->monthly_deduction,
                'term_months' => $loan->term_months,
                'total_amount' => (float) $loan->total_amount,
                'total_paid' => (float) $loan->total_paid,
                'remaining_balance' => (float) $loan->remaining_balance,
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'status_color' => $loan->status->color(),
                'start_date' => $loan->start_date?->format('M d, Y'),
                'expected_end_date' => $loan->expected_end_date?->format('M d, Y'),
                'actual_end_date' => $loan->actual_end_date?->format('M d, Y'),
                'progress_percentage' => $loan->getProgressPercentage(),
                'notes' => $loan->notes,
                'payments' => $loan->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'balance_before' => (float) $p->balance_before,
                    'balance_after' => (float) $p->balance_after,
                    'payment_date' => $p->payment_date?->format('M d, Y'),
                    'payment_source' => $p->payment_source,
                ])->toArray(),
            ],
        ]);
    }
}
