<?php

namespace App\Http\Controllers\Api;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordLoanPaymentRequest;
use App\Http\Requests\StoreEmployeeLoanRequest;
use App\Http\Requests\UpdateEmployeeLoanRequest;
use App\Http\Requests\UpdateLoanStatusRequest;
use App\Http\Resources\EmployeeLoanListResource;
use App\Http\Resources\EmployeeLoanResource;
use App\Http\Resources\LoanPaymentResource;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EmployeeLoanController extends Controller
{
    /**
     * Display a listing of employee loans.
     *
     * Supports filtering by status, loan_type, and employee_id.
     */
    public function index(Request $request): AnonymousResourceCollection
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

        $perPage = $request->input('per_page', 25);

        if ($request->boolean('paginate', true)) {
            return EmployeeLoanListResource::collection($query->paginate($perPage));
        }

        return EmployeeLoanListResource::collection($query->get());
    }

    /**
     * Store a newly created employee loan.
     */
    public function store(StoreEmployeeLoanRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $loan = EmployeeLoan::create($request->validatedWithDefaults());

        $loan->load(['employee.department', 'employee.position']);

        return (new EmployeeLoanResource($loan))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified employee loan.
     */
    public function show(EmployeeLoan $loan): EmployeeLoanResource
    {
        Gate::authorize('can-manage-organization');

        $loan->load([
            'employee.department',
            'employee.position',
            'payments' => fn ($q) => $q->orderBy('payment_date', 'desc'),
        ]);

        return new EmployeeLoanResource($loan);
    }

    /**
     * Update the specified employee loan.
     */
    public function update(
        UpdateEmployeeLoanRequest $request,
        EmployeeLoan $loan
    ): EmployeeLoanResource {
        Gate::authorize('can-manage-organization');

        if ($loan->status !== LoanStatus::Active && $loan->status !== LoanStatus::OnHold) {
            abort(422, 'Only active or on-hold loans can be updated.');
        }

        $loan->update($request->validated());

        $loan->load(['employee.department', 'employee.position']);

        return new EmployeeLoanResource($loan);
    }

    /**
     * Remove the specified employee loan.
     */
    public function destroy(EmployeeLoan $loan): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        if ($loan->payments()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a loan that has recorded payments.',
            ], 422);
        }

        $loan->delete();

        return response()->json([
            'message' => 'Loan deleted successfully.',
        ]);
    }

    /**
     * Update the status of an employee loan.
     */
    public function updateStatus(
        UpdateLoanStatusRequest $request,
        EmployeeLoan $loan
    ): EmployeeLoanResource {
        Gate::authorize('can-manage-organization');

        $newStatus = LoanStatus::from($request->validated('status'));
        $notes = $request->validated('notes');

        match ($newStatus) {
            LoanStatus::OnHold => $loan->putOnHold($notes),
            LoanStatus::Active => $loan->resume(),
            LoanStatus::Completed => $loan->markAsCompleted(),
            LoanStatus::Cancelled => $loan->cancel($notes),
        };

        $loan->load(['employee.department', 'employee.position']);

        return new EmployeeLoanResource($loan);
    }

    /**
     * Record a manual payment against a loan.
     */
    public function recordPayment(
        RecordLoanPaymentRequest $request,
        EmployeeLoan $loan
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        if ($loan->status === LoanStatus::Completed) {
            return response()->json([
                'message' => 'Cannot record payment on a completed loan.',
            ], 422);
        }

        if ($loan->status === LoanStatus::Cancelled) {
            return response()->json([
                'message' => 'Cannot record payment on a cancelled loan.',
            ], 422);
        }

        $payment = $loan->recordPayment(
            amount: (float) $request->validated('amount'),
            paymentDate: $request->validated('payment_date'),
            paymentSource: $request->validated('payment_source', 'manual'),
            notes: $request->validated('notes')
        );

        $loan->refresh();
        $loan->load(['employee.department', 'employee.position']);

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => new LoanPaymentResource($payment),
            'loan' => new EmployeeLoanResource($loan),
        ]);
    }

    /**
     * Get loans for a specific employee.
     */
    public function employeeLoans(
        Request $request,
        Employee $employee
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $query = $employee->loans()
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return EmployeeLoanResource::collection($query->get());
    }
}
