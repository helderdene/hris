<?php

namespace App\Http\Controllers\Api;

use App\Enums\ManualAttendanceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualAttendanceRequestRequest;
use App\Http\Requests\UpdateManualAttendanceRequestRequest;
use App\Http\Resources\ManualAttendanceRequestResource;
use App\Models\Employee;
use App\Models\ManualAttendanceRequest;
use App\Services\ManualAttendanceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ManualAttendanceRequestController extends Controller
{
    public function __construct(
        protected ManualAttendanceRequestService $service
    ) {}

    /**
     * Display the current user's manual attendance requests.
     */
    public function myRequests(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if (! $employee) {
            return ManualAttendanceRequestResource::collection(collect());
        }

        $query = ManualAttendanceRequest::query()
            ->forEmployee($employee)
            ->with(['employee'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('year')) {
            $query->whereYear('attendance_date', $request->input('year'));
        }

        return ManualAttendanceRequestResource::collection($query->get());
    }

    /**
     * Store a newly created request as a draft.
     */
    public function store(StoreManualAttendanceRequestRequest $request): JsonResponse
    {
        $manualRequest = ManualAttendanceRequest::create($request->validatedWithDefaults());

        $manualRequest->load(['employee.department', 'employee.position']);

        return (new ManualAttendanceRequestResource($manualRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified request.
     */
    public function show(ManualAttendanceRequest $manualAttendanceRequest): ManualAttendanceRequestResource
    {
        $this->authorizeOwnerOrApprover($manualAttendanceRequest);

        $manualAttendanceRequest->load(['employee.department', 'employee.position']);

        return new ManualAttendanceRequestResource($manualAttendanceRequest);
    }

    /**
     * Update the specified request (drafts only).
     */
    public function update(
        UpdateManualAttendanceRequestRequest $request,
        ManualAttendanceRequest $manualAttendanceRequest
    ): ManualAttendanceRequestResource {
        $manualAttendanceRequest->update($request->validated());
        $manualAttendanceRequest->load(['employee.department', 'employee.position']);

        return new ManualAttendanceRequestResource($manualAttendanceRequest);
    }

    /**
     * Submit a draft request for approval.
     */
    public function submit(
        Request $request,
        ManualAttendanceRequest $manualAttendanceRequest
    ): ManualAttendanceRequestResource {
        $this->authorizeOwner($manualAttendanceRequest);

        $result = $this->service->submit($manualAttendanceRequest);

        return new ManualAttendanceRequestResource($result);
    }

    /**
     * Cancel a draft or pending request.
     */
    public function cancel(
        Request $request,
        ManualAttendanceRequest $manualAttendanceRequest
    ): ManualAttendanceRequestResource {
        $this->authorizeOwner($manualAttendanceRequest);

        $result = $this->service->cancel(
            $manualAttendanceRequest,
            $request->input('reason')
        );

        return new ManualAttendanceRequestResource($result);
    }

    /**
     * Delete a draft request.
     */
    public function destroy(ManualAttendanceRequest $manualAttendanceRequest): JsonResponse
    {
        $this->authorizeOwner($manualAttendanceRequest);

        if ($manualAttendanceRequest->status !== ManualAttendanceRequestStatus::Draft) {
            return response()->json([
                'message' => 'Only draft requests can be deleted.',
            ], 422);
        }

        $manualAttendanceRequest->delete();

        return response()->json([
            'message' => 'Manual attendance request deleted successfully.',
        ]);
    }

    /**
     * Get pending requests that the current user can decide.
     */
    public function pendingApprovals(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if (! $employee) {
            return ManualAttendanceRequestResource::collection(collect());
        }

        $requests = ManualAttendanceRequest::query()
            ->forApprover($employee)
            ->with(['employee.department', 'employee.position'])
            ->orderBy('submitted_at')
            ->get();

        return ManualAttendanceRequestResource::collection($requests);
    }

    /**
     * Approve a manual attendance request.
     */
    public function approve(
        Request $request,
        ManualAttendanceRequest $manualAttendanceRequest
    ): ManualAttendanceRequestResource {
        $user = $request->user();

        $result = $this->service->approve(
            $manualAttendanceRequest,
            $user,
            $request->input('remarks')
        );

        return new ManualAttendanceRequestResource($result);
    }

    /**
     * Reject a manual attendance request.
     */
    public function reject(
        Request $request,
        ManualAttendanceRequest $manualAttendanceRequest
    ): ManualAttendanceRequestResource {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $result = $this->service->reject(
            $manualAttendanceRequest,
            $user,
            $request->input('reason')
        );

        return new ManualAttendanceRequestResource($result);
    }

    /**
     * Ensure the current user owns this request.
     */
    protected function authorizeOwner(ManualAttendanceRequest $manualAttendanceRequest): void
    {
        $user = request()->user();
        $employee = Employee::query()->where('user_id', $user?->id)->first();

        if (! $employee || $employee->id !== $manualAttendanceRequest->employee_id) {
            throw ValidationException::withMessages([
                'employee' => 'You are not authorized to act on this request.',
            ]);
        }
    }

    /**
     * Ensure the current user owns this request OR is an eligible approver.
     */
    protected function authorizeOwnerOrApprover(ManualAttendanceRequest $manualAttendanceRequest): void
    {
        $user = request()->user();
        $employee = Employee::query()->where('user_id', $user?->id)->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => 'You are not authorized to view this request.',
            ]);
        }

        if ($employee->id === $manualAttendanceRequest->employee_id) {
            return;
        }

        if ($manualAttendanceRequest->canBeDecidedBy($user)) {
            return;
        }

        // Allow viewing for users who already decided on this request (history).
        if ($manualAttendanceRequest->decided_by_user_id === $user->id) {
            return;
        }

        throw ValidationException::withMessages([
            'employee' => 'You are not authorized to view this request.',
        ]);
    }
}
