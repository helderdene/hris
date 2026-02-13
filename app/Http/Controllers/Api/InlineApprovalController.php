<?php

namespace App\Http\Controllers\Api;

use App\Events\ActionCenterUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApproveLeaveRequest;
use App\Http\Requests\Api\ApproveRequisitionRequest;
use App\Http\Requests\Api\RejectLeaveRequest;
use App\Http\Requests\Api\RejectRequisitionRequest;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\LeaveApplication;
use App\Services\JobRequisitionService;
use App\Services\LeaveApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Handle inline approval/rejection actions from the Action Center Dashboard.
 *
 * Provides quick approve/reject endpoints that return JSON responses
 * for seamless inline updates without page navigation.
 */
class InlineApprovalController extends Controller
{
    public function __construct(
        protected LeaveApplicationService $leaveApplicationService,
        protected JobRequisitionService $jobRequisitionService
    ) {}

    /**
     * Approve a leave application inline.
     */
    public function approveLeave(ApproveLeaveRequest $request, LeaveApplication $leaveApplication): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found.',
            ], 403);
        }

        try {
            $application = $this->leaveApplicationService->approve(
                $leaveApplication,
                $employee,
                $validated['remarks'] ?? null
            );

            // Broadcast update to all dashboard subscribers
            $this->broadcastActionCenterUpdate('leave_approved', [
                'leave_application_id' => $application->id,
                'status' => $application->status->value,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave application approved successfully.',
                'data' => [
                    'id' => $application->id,
                    'status' => $application->status->value,
                    'status_label' => $application->status->label(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Reject a leave application inline.
     */
    public function rejectLeave(RejectLeaveRequest $request, LeaveApplication $leaveApplication): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found.',
            ], 403);
        }

        try {
            $application = $this->leaveApplicationService->reject(
                $leaveApplication,
                $employee,
                $validated['reason']
            );

            $this->broadcastActionCenterUpdate('leave_rejected', [
                'leave_application_id' => $application->id,
                'status' => $application->status->value,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave application rejected.',
                'data' => [
                    'id' => $application->id,
                    'status' => $application->status->value,
                    'status_label' => $application->status->label(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Approve a job requisition inline.
     */
    public function approveRequisition(ApproveRequisitionRequest $request, JobRequisition $jobRequisition): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found.',
            ], 403);
        }

        try {
            $requisition = $this->jobRequisitionService->approve(
                $jobRequisition,
                $employee,
                $validated['remarks'] ?? null
            );

            $this->broadcastActionCenterUpdate('requisition_approved', [
                'job_requisition_id' => $requisition->id,
                'status' => $requisition->status->value,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job requisition approved successfully.',
                'data' => [
                    'id' => $requisition->id,
                    'status' => $requisition->status->value,
                    'status_label' => $requisition->status->label(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Reject a job requisition inline.
     */
    public function rejectRequisition(RejectRequisitionRequest $request, JobRequisition $jobRequisition): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found.',
            ], 403);
        }

        try {
            $requisition = $this->jobRequisitionService->reject(
                $jobRequisition,
                $employee,
                $validated['reason']
            );

            $this->broadcastActionCenterUpdate('requisition_rejected', [
                'job_requisition_id' => $requisition->id,
                'status' => $requisition->status->value,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job requisition rejected.',
                'data' => [
                    'id' => $requisition->id,
                    'status' => $requisition->status->value,
                    'status_label' => $requisition->status->label(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Broadcast an action center update event.
     *
     * @param  array<string, mixed>  $data
     */
    protected function broadcastActionCenterUpdate(string $action, array $data): void
    {
        $tenant = tenant();

        if ($tenant) {
            broadcast(new ActionCenterUpdated($action, $data))->toOthers();
        }
    }
}
