<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveApprovalDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveLeaveApplicationRequest;
use App\Http\Requests\RejectLeaveApplicationRequest;
use App\Http\Resources\LeaveApplicationResource;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\LeaveApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveApprovalController extends Controller
{
    public function __construct(
        protected LeaveApplicationService $leaveApplicationService
    ) {}

    /**
     * Get leave applications pending the current user's approval.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return LeaveApplicationResource::collection(collect());
        }

        $applications = LeaveApplication::query()
            ->where('status', LeaveApplicationStatus::Pending)
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->where('decision', LeaveApprovalDecision::Pending);
            })
            ->where(function ($q) {
                // Only show applications where the current approval level matches
                // the approval level of this approver's pending approval
                $q->whereRaw('current_approval_level = (
                    SELECT approval_level
                    FROM leave_application_approvals
                    WHERE leave_application_id = leave_applications.id
                    AND decision = ?
                    ORDER BY approval_level ASC
                    LIMIT 1
                )', [LeaveApprovalDecision::Pending->value]);
            })
            ->with([
                'employee.department',
                'employee.position',
                'leaveType',
                'approvals.approverEmployee',
            ])
            ->orderBy('submitted_at', 'asc')
            ->get();

        return LeaveApplicationResource::collection($applications);
    }

    /**
     * Get summary of pending approvals for the current user.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return response()->json([
                'pending_count' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
            ]);
        }

        $pendingCount = LeaveApplication::query()
            ->where('status', LeaveApplicationStatus::Pending)
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->where('decision', LeaveApprovalDecision::Pending);
            })
            ->count();

        $approvedToday = LeaveApplication::query()
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->where('decision', LeaveApprovalDecision::Approved)
                    ->whereDate('decided_at', today());
            })
            ->count();

        $rejectedToday = LeaveApplication::query()
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->where('decision', LeaveApprovalDecision::Rejected)
                    ->whereDate('decided_at', today());
            })
            ->count();

        return response()->json([
            'pending_count' => $pendingCount,
            'approved_today' => $approvedToday,
            'rejected_today' => $rejectedToday,
        ]);
    }

    /**
     * Approve a leave application.
     */
    public function approve(
        ApproveLeaveApplicationRequest $request,
        LeaveApplication $leaveApplication
    ): LeaveApplicationResource {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $application = $this->leaveApplicationService->approve(
            $leaveApplication,
            $employee,
            $request->validated('remarks')
        );

        return new LeaveApplicationResource($application);
    }

    /**
     * Reject a leave application.
     */
    public function reject(
        RejectLeaveApplicationRequest $request,
        LeaveApplication $leaveApplication
    ): LeaveApplicationResource {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $application = $this->leaveApplicationService->reject(
            $leaveApplication,
            $employee,
            $request->validated('reason')
        );

        return new LeaveApplicationResource($application);
    }

    /**
     * Get approval history for the current user.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return LeaveApplicationResource::collection(collect());
        }

        $applications = LeaveApplication::query()
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->whereIn('decision', [
                        LeaveApprovalDecision::Approved,
                        LeaveApprovalDecision::Rejected,
                    ]);
            })
            ->with([
                'employee.department',
                'employee.position',
                'leaveType',
                'approvals.approverEmployee',
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        return LeaveApplicationResource::collection($applications);
    }
}
