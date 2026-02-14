<?php

namespace App\Http\Controllers\Api;

use App\Enums\JobRequisitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobRequisitionRequest;
use App\Http\Requests\UpdateJobRequisitionRequest;
use App\Http\Resources\JobRequisitionResource;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Services\JobRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class JobRequisitionController extends Controller
{
    public function __construct(
        protected JobRequisitionService $jobRequisitionService
    ) {}

    /**
     * Display a listing of job requisitions.
     *
     * Supports filtering by status, urgency, department_id.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = JobRequisition::query()
            ->with(['position', 'department', 'requestedByEmployee.department', 'requestedByEmployee.position', 'approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->input('urgency'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $perPage = $request->input('per_page', 25);

        if ($request->boolean('paginate', true)) {
            return JobRequisitionResource::collection($query->paginate($perPage));
        }

        return JobRequisitionResource::collection($query->get());
    }

    /**
     * Get job requisitions for the current user's employee.
     */
    public function myRequisitions(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return JobRequisitionResource::collection(collect());
        }

        $query = JobRequisition::query()
            ->forEmployee($employee)
            ->with(['position', 'department', 'approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return JobRequisitionResource::collection($query->get());
    }

    /**
     * Store a newly created job requisition.
     */
    public function store(StoreJobRequisitionRequest $request): JsonResponse
    {
        $requisition = JobRequisition::create($request->validatedWithDefaults());

        $requisition->load(['position', 'department', 'requestedByEmployee']);

        return (new JobRequisitionResource($requisition))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified job requisition.
     */
    public function show(JobRequisition $jobRequisition): JobRequisitionResource
    {
        $jobRequisition->load([
            'position',
            'department',
            'requestedByEmployee.department',
            'requestedByEmployee.position',
            'approvals.approverEmployee',
        ]);

        return new JobRequisitionResource($jobRequisition);
    }

    /**
     * Update the specified job requisition.
     */
    public function update(
        UpdateJobRequisitionRequest $request,
        JobRequisition $jobRequisition
    ): JobRequisitionResource {
        $jobRequisition->update($request->validated());

        $jobRequisition->load(['position', 'department', 'requestedByEmployee']);

        return new JobRequisitionResource($jobRequisition);
    }

    /**
     * Submit a draft requisition for approval.
     */
    public function submit(
        Request $request,
        JobRequisition $jobRequisition
    ): JobRequisitionResource {
        $requisition = $this->jobRequisitionService->submit($jobRequisition);

        return new JobRequisitionResource($requisition);
    }

    /**
     * Cancel a job requisition.
     */
    public function cancel(
        Request $request,
        JobRequisition $jobRequisition
    ): JobRequisitionResource {
        $reason = $request->input('reason');

        $requisition = $this->jobRequisitionService->cancel($jobRequisition, $reason);

        return new JobRequisitionResource($requisition);
    }

    /**
     * Remove the specified job requisition.
     */
    public function destroy(JobRequisition $jobRequisition): JsonResponse
    {
        if ($jobRequisition->status !== JobRequisitionStatus::Draft) {
            return response()->json([
                'message' => 'Only draft requisitions can be deleted.',
            ], 422);
        }

        $jobRequisition->delete();

        return response()->json([
            'message' => 'Job requisition deleted successfully.',
        ]);
    }
}
