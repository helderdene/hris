<?php

namespace App\Http\Controllers\Api;

use App\Enums\OvertimeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOvertimeRequestRequest;
use App\Http\Requests\UpdateOvertimeRequestRequest;
use App\Http\Resources\OvertimeRequestResource;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\OvertimeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OvertimeRequestController extends Controller
{
    public function __construct(
        protected OvertimeRequestService $overtimeRequestService
    ) {}

    /**
     * Display a listing of overtime requests.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = OvertimeRequest::query()
            ->with(['employee.department', 'employee.position', 'approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('overtime_type')) {
            $query->where('overtime_type', $request->input('overtime_type'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('year')) {
            $query->whereYear('overtime_date', $request->input('year'));
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('overtime_date', [$request->input('date_from'), $request->input('date_to')]);
        }

        $perPage = $request->input('per_page', 25);

        if ($request->boolean('paginate', true)) {
            return OvertimeRequestResource::collection($query->paginate($perPage));
        }

        return OvertimeRequestResource::collection($query->get());
    }

    /**
     * Get overtime requests for the current user's employee.
     */
    public function myRequests(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return OvertimeRequestResource::collection(collect());
        }

        $query = OvertimeRequest::query()
            ->forEmployee($employee)
            ->with(['approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('year')) {
            $query->whereYear('overtime_date', $request->input('year'));
        }

        return OvertimeRequestResource::collection($query->get());
    }

    /**
     * Store a newly created overtime request.
     */
    public function store(StoreOvertimeRequestRequest $request): JsonResponse
    {
        $overtimeRequest = OvertimeRequest::create($request->validatedWithDefaults());

        $overtimeRequest->load(['employee.department', 'employee.position']);

        return (new OvertimeRequestResource($overtimeRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified overtime request.
     */
    public function show(OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $overtimeRequest->load([
            'employee.department',
            'employee.position',
            'approvals.approverEmployee',
            'dailyTimeRecord',
        ]);

        return new OvertimeRequestResource($overtimeRequest);
    }

    /**
     * Update the specified overtime request.
     */
    public function update(
        UpdateOvertimeRequestRequest $request,
        OvertimeRequest $overtimeRequest
    ): OvertimeRequestResource {
        $overtimeRequest->update($request->validated());

        $overtimeRequest->load(['employee.department', 'employee.position']);

        return new OvertimeRequestResource($overtimeRequest);
    }

    /**
     * Submit a draft request for approval.
     */
    public function submit(
        Request $request,
        OvertimeRequest $overtimeRequest
    ): OvertimeRequestResource {
        $result = $this->overtimeRequestService->submit($overtimeRequest);

        return new OvertimeRequestResource($result);
    }

    /**
     * Cancel an overtime request.
     */
    public function cancel(
        Request $request,
        OvertimeRequest $overtimeRequest
    ): OvertimeRequestResource {
        $reason = $request->input('reason');

        $result = $this->overtimeRequestService->cancel($overtimeRequest, $reason);

        return new OvertimeRequestResource($result);
    }

    /**
     * Remove the specified overtime request.
     */
    public function destroy(OvertimeRequest $overtimeRequest): JsonResponse
    {
        if ($overtimeRequest->status !== OvertimeRequestStatus::Draft) {
            return response()->json([
                'message' => 'Only draft requests can be deleted.',
            ], 422);
        }

        $overtimeRequest->delete();

        return response()->json([
            'message' => 'Overtime request deleted successfully.',
        ]);
    }

    /**
     * Get pending overtime approvals for the current user.
     */
    public function pendingApprovals(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return OvertimeRequestResource::collection(collect());
        }

        $requests = OvertimeRequest::query()
            ->forApprover($employee)
            ->with(['employee.department', 'employee.position', 'approvals.approverEmployee'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        return OvertimeRequestResource::collection($requests);
    }

    /**
     * Approve an overtime request.
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $result = $this->overtimeRequestService->approve(
            $overtimeRequest,
            $employee,
            $request->input('remarks')
        );

        return new OvertimeRequestResource($result);
    }

    /**
     * Reject an overtime request.
     */
    public function reject(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $result = $this->overtimeRequestService->reject(
            $overtimeRequest,
            $employee,
            $request->input('reason')
        );

        return new OvertimeRequestResource($result);
    }

    /**
     * Get overtime requests for a specific employee.
     */
    public function employeeRequests(
        Request $request,
        Employee $employee
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $query = OvertimeRequest::query()
            ->forEmployee($employee)
            ->with(['approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('year')) {
            $query->whereYear('overtime_date', $request->input('year'));
        }

        return OvertimeRequestResource::collection($query->get());
    }
}
