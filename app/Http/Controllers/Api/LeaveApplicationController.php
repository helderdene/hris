<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveApplicationRequest;
use App\Http\Requests\UpdateLeaveApplicationRequest;
use App\Http\Resources\LeaveApplicationResource;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\LeaveApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class LeaveApplicationController extends Controller
{
    public function __construct(
        protected LeaveApplicationService $leaveApplicationService
    ) {}

    /**
     * Display a listing of leave applications.
     *
     * Supports filtering by status, leave_type_id, and employee_id.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = LeaveApplication::query()
            ->with(['employee.department', 'employee.position', 'leaveType', 'approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->input('leave_type_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->input('year'));
        }

        $perPage = $request->input('per_page', 25);

        if ($request->boolean('paginate', true)) {
            return LeaveApplicationResource::collection($query->paginate($perPage));
        }

        return LeaveApplicationResource::collection($query->get());
    }

    /**
     * Get leave applications for the current user's employee.
     */
    public function myApplications(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return LeaveApplicationResource::collection(collect());
        }

        $query = LeaveApplication::query()
            ->forEmployee($employee)
            ->with(['leaveType', 'approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->input('year'));
        }

        return LeaveApplicationResource::collection($query->get());
    }

    /**
     * Store a newly created leave application.
     */
    public function store(StoreLeaveApplicationRequest $request): JsonResponse
    {
        $data = $request->validatedWithCalculations();

        if ($request->hasFile('attachment')) {
            $data = array_merge($data, $this->storeAttachment($request, $data['employee_id']));
        }

        $application = LeaveApplication::create($data);

        $application->load(['employee.department', 'employee.position', 'leaveType']);

        return (new LeaveApplicationResource($application))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified leave application.
     */
    public function show(LeaveApplication $leaveApplication): LeaveApplicationResource
    {
        $leaveApplication->load([
            'employee.department',
            'employee.position',
            'leaveType',
            'leaveBalance',
            'approvals.approverEmployee',
        ]);

        return new LeaveApplicationResource($leaveApplication);
    }

    /**
     * Update the specified leave application.
     */
    public function update(
        UpdateLeaveApplicationRequest $request,
        LeaveApplication $leaveApplication
    ): LeaveApplicationResource {
        $data = $request->validatedWithCalculations();

        if ($request->boolean('remove_attachment') || $request->hasFile('attachment')) {
            $this->deleteAttachment($leaveApplication);
            $data = array_merge($data, [
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
                'attachment_size' => null,
            ]);
        }

        if ($request->hasFile('attachment')) {
            $data = array_merge($data, $this->storeAttachment($request, $leaveApplication->employee_id));
        }

        $leaveApplication->update($data);

        $leaveApplication->load(['employee.department', 'employee.position', 'leaveType']);

        return new LeaveApplicationResource($leaveApplication);
    }

    /**
     * Persist an uploaded supporting document and return the column values.
     *
     * @return array<string, mixed>
     */
    protected function storeAttachment(Request $request, int $employeeId): array
    {
        $file = $request->file('attachment');

        $path = $file->store("leave-applications/{$employeeId}", 'local');

        return [
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getClientMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    /**
     * Delete an existing attachment from storage if present.
     */
    protected function deleteAttachment(LeaveApplication $leaveApplication): void
    {
        if ($leaveApplication->attachment_path) {
            Storage::disk('local')->delete($leaveApplication->attachment_path);
        }
    }

    /**
     * Download the attached supporting document.
     */
    public function downloadAttachment(LeaveApplication $leaveApplication): mixed
    {
        if (! $leaveApplication->attachment_path
            || ! Storage::disk('local')->exists($leaveApplication->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $leaveApplication->attachment_path,
            $leaveApplication->attachment_name ?? 'attachment'
        );
    }

    /**
     * Submit a draft application for approval.
     */
    public function submit(
        Request $request,
        LeaveApplication $leaveApplication
    ): LeaveApplicationResource {
        $application = $this->leaveApplicationService->submit($leaveApplication);

        return new LeaveApplicationResource($application);
    }

    /**
     * Cancel a leave application.
     */
    public function cancel(
        Request $request,
        LeaveApplication $leaveApplication
    ): LeaveApplicationResource {
        $reason = $request->input('reason');

        $application = $this->leaveApplicationService->cancel($leaveApplication, $reason);

        return new LeaveApplicationResource($application);
    }

    /**
     * Remove the specified leave application.
     */
    public function destroy(LeaveApplication $leaveApplication): JsonResponse
    {
        if ($leaveApplication->status !== LeaveApplicationStatus::Draft) {
            return response()->json([
                'message' => 'Only draft applications can be deleted.',
            ], 422);
        }

        $leaveApplication->delete();

        return response()->json([
            'message' => 'Leave application deleted successfully.',
        ]);
    }

    /**
     * Get leave applications for a specific employee.
     */
    public function employeeApplications(
        Request $request,
        Employee $employee
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $query = $employee->leaveApplications()
            ->with(['leaveType', 'approvals.approverEmployee'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->input('year'));
        }

        return LeaveApplicationResource::collection($query->get());
    }
}
