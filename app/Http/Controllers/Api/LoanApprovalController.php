<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveLoanApplicationRequest;
use App\Http\Requests\RejectLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\Employee;
use App\Models\LoanApplication;
use App\Services\LoanApplicationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LoanApprovalController extends Controller
{
    public function __construct(
        protected LoanApplicationService $loanApplicationService
    ) {}

    /**
     * Get pending loan applications for HR review.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $applications = LoanApplication::query()
            ->pending()
            ->with(['employee.department', 'employee.position'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        return LoanApplicationResource::collection($applications);
    }

    /**
     * Approve a loan application.
     */
    public function approve(ApproveLoanApplicationRequest $request, string $tenant, LoanApplication $loanApplication): LoanApplicationResource
    {
        Gate::authorize('can-manage-organization');

        $user = $request->user();
        $reviewer = Employee::where('user_id', $user->id)->firstOrFail();

        $application = $this->loanApplicationService->approve(
            $loanApplication,
            $reviewer,
            $request->validated()
        );

        return new LoanApplicationResource($application);
    }

    /**
     * Reject a loan application.
     */
    public function reject(RejectLoanApplicationRequest $request, string $tenant, LoanApplication $loanApplication): LoanApplicationResource
    {
        Gate::authorize('can-manage-organization');

        $user = $request->user();
        $reviewer = Employee::where('user_id', $user->id)->firstOrFail();

        $application = $this->loanApplicationService->reject(
            $loanApplication,
            $reviewer,
            $request->validated()['remarks']
        );

        return new LoanApplicationResource($application);
    }
}
