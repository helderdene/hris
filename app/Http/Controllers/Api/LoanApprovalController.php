<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveApprovalDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveLoanApplicationRequest;
use App\Http\Requests\RejectLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\Employee;
use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use App\Services\LoanApplicationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LoanApprovalController extends Controller
{
    public function __construct(
        protected LoanApplicationService $loanApplicationService
    ) {}

    /**
     * Get pending loan applications across the org (HR-wide view).
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $applications = LoanApplication::query()
            ->pending()
            ->with(['employee.department', 'employee.position', 'approvals.approverEmployee'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        return LoanApplicationResource::collection($applications);
    }

    /**
     * Get pending approvals where the current user is the assigned approver
     * at the current chain level. Used by the My Loan Approvals queue.
     */
    public function myPending(Request $request): AnonymousResourceCollection
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return LoanApplicationResource::collection(collect());
        }

        $approvals = LoanApplicationApproval::query()
            ->forApprover($employee)
            ->where('decision', LeaveApprovalDecision::Pending)
            ->whereHas('loanApplication', function ($query) {
                $query->whereColumn('current_approval_level', 'loan_application_approvals.approval_level');
            })
            ->with(['loanApplication.employee.department', 'loanApplication.employee.position', 'loanApplication.approvals'])
            ->orderBy('deadline_at')
            ->get();

        $applications = $approvals
            ->map(fn (LoanApplicationApproval $a) => $a->loanApplication)
            ->filter()
            ->values();

        return LoanApplicationResource::collection($applications);
    }

    /**
     * Approve a loan application at the caller's current level.
     */
    public function approve(
        ApproveLoanApplicationRequest $request,
        LoanApplication $loanApplication
    ): LoanApplicationResource {
        $approver = $this->resolveApprover($request, $loanApplication);

        $application = $this->loanApplicationService->approve(
            $loanApplication,
            $approver,
            $request->validated()
        );

        return new LoanApplicationResource($application);
    }

    /**
     * Reject a loan application at the caller's current level.
     */
    public function reject(
        RejectLoanApplicationRequest $request,
        LoanApplication $loanApplication
    ): LoanApplicationResource {
        $approver = $this->resolveApprover($request, $loanApplication);

        $application = $this->loanApplicationService->reject(
            $loanApplication,
            $approver,
            $request->validated()['remarks']
        );

        return new LoanApplicationResource($application);
    }

    /**
     * Resolve the current user's Employee record and ensure they are the
     * assigned approver at the application's current chain level.
     *
     * @throws ValidationException
     */
    protected function resolveApprover(Request $request, LoanApplication $application): Employee
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'approver' => 'No employee profile linked to your account.',
            ]);
        }

        $isAssigned = $application->approvals()
            ->where('approval_level', $application->current_approval_level)
            ->where('approver_employee_id', $employee->id)
            ->exists();

        if (! $isAssigned) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the current-level approver for this application.',
            ]);
        }

        return $employee;
    }
}
