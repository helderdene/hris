<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanApplicationRequest;
use App\Http\Requests\UpdateLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\Employee;
use App\Models\LoanApplication;
use App\Services\LoanApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class LoanApplicationController extends Controller
{
    public function __construct(
        protected LoanApplicationService $loanApplicationService
    ) {}

    /**
     * Display a listing of loan applications (HR view).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = LoanApplication::query()
            ->with(['employee.department', 'employee.position', 'reviewer'])
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

        $perPage = $request->input('per_page', 25);

        return LoanApplicationResource::collection($query->paginate($perPage));
    }

    /**
     * Get loan applications for the current user's employee.
     */
    public function myApplications(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return LoanApplicationResource::collection(collect());
        }

        $query = LoanApplication::query()
            ->forEmployee($employee)
            ->with(['reviewer'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return LoanApplicationResource::collection($query->get());
    }

    /**
     * Store a newly created loan application.
     */
    public function store(StoreLoanApplicationRequest $request): LoanApplicationResource
    {
        $validated = $request->validated();

        // Handle file uploads
        $documents = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('loan-applications', 'tenant');
                $documents[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }
        }

        $application = LoanApplication::create([
            'employee_id' => $validated['employee_id'],
            'loan_type' => $validated['loan_type'],
            'amount_requested' => $validated['amount_requested'],
            'term_months' => $validated['term_months'],
            'purpose' => $validated['purpose'] ?? null,
            'documents' => ! empty($documents) ? $documents : null,
            'created_by' => auth()->id(),
        ]);

        return new LoanApplicationResource($application->load('employee'));
    }

    /**
     * Display the specified loan application.
     */
    public function show(LoanApplication $loanApplication): LoanApplicationResource
    {
        $loanApplication->load(['employee.department', 'employee.position', 'reviewer', 'employeeLoan']);

        return new LoanApplicationResource($loanApplication);
    }

    /**
     * Update a draft loan application.
     */
    public function update(UpdateLoanApplicationRequest $request, LoanApplication $loanApplication): LoanApplicationResource
    {
        $validated = $request->validated();

        // Handle file uploads
        if ($request->hasFile('documents')) {
            $documents = $loanApplication->documents ?? [];
            foreach ($request->file('documents') as $file) {
                $path = $file->store('loan-applications', 'tenant');
                $documents[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }
            $validated['documents'] = $documents;
        }

        unset($validated['documents']);
        $loanApplication->update($validated);

        return new LoanApplicationResource($loanApplication->fresh('employee'));
    }

    /**
     * Delete a draft loan application.
     */
    public function destroy(LoanApplication $loanApplication): JsonResponse
    {
        if (! $loanApplication->status->canBeEdited()) {
            return response()->json(['message' => 'Only draft applications can be deleted.'], 422);
        }

        // Clean up uploaded files
        if ($loanApplication->documents) {
            foreach ($loanApplication->documents as $doc) {
                if (isset($doc['path'])) {
                    Storage::disk('tenant')->delete($doc['path']);
                }
            }
        }

        $loanApplication->delete();

        return response()->json(['message' => 'Loan application deleted.']);
    }

    /**
     * Submit a draft loan application for review.
     */
    public function submit(LoanApplication $loanApplication): LoanApplicationResource
    {
        $application = $this->loanApplicationService->submit($loanApplication);

        return new LoanApplicationResource($application);
    }

    /**
     * Cancel a loan application.
     */
    public function cancel(Request $request, LoanApplication $loanApplication): LoanApplicationResource
    {
        $application = $this->loanApplicationService->cancel(
            $loanApplication,
            $request->input('reason')
        );

        return new LoanApplicationResource($application);
    }
}
