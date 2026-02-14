<?php

namespace App\Http\Controllers\Api;

use App\Enums\JobRequisitionStatus;
use App\Enums\LeaveApprovalDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveJobRequisitionRequest;
use App\Http\Requests\RejectJobRequisitionRequest;
use App\Http\Resources\JobRequisitionResource;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Services\JobRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobRequisitionApprovalController extends Controller
{
    public function __construct(
        protected JobRequisitionService $jobRequisitionService
    ) {}

    /**
     * Get job requisitions pending the current user's approval.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return JobRequisitionResource::collection(collect());
        }

        $requisitions = JobRequisition::query()
            ->where('status', JobRequisitionStatus::Pending)
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->where('decision', LeaveApprovalDecision::Pending);
            })
            ->where(function ($q) {
                $q->whereRaw('current_approval_level = (
                    SELECT approval_level
                    FROM job_requisition_approvals
                    WHERE job_requisition_id = job_requisitions.id
                    AND decision = ?
                    ORDER BY approval_level ASC
                    LIMIT 1
                )', [LeaveApprovalDecision::Pending->value]);
            })
            ->with([
                'position',
                'department',
                'requestedByEmployee.department',
                'requestedByEmployee.position',
                'approvals.approverEmployee',
            ])
            ->orderBy('submitted_at', 'asc')
            ->get();

        return JobRequisitionResource::collection($requisitions);
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

        $pendingCount = JobRequisition::query()
            ->where('status', JobRequisitionStatus::Pending)
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->where('decision', LeaveApprovalDecision::Pending);
            })
            ->count();

        $approvedToday = JobRequisition::query()
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->where('decision', LeaveApprovalDecision::Approved)
                    ->whereDate('decided_at', today());
            })
            ->count();

        $rejectedToday = JobRequisition::query()
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
     * Get approval history for the current user.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return JobRequisitionResource::collection(collect());
        }

        $requisitions = JobRequisition::query()
            ->whereHas('approvals', function ($q) use ($employee) {
                $q->where('approver_employee_id', $employee->id)
                    ->whereIn('decision', [
                        LeaveApprovalDecision::Approved,
                        LeaveApprovalDecision::Rejected,
                    ]);
            })
            ->with([
                'position',
                'department',
                'requestedByEmployee.department',
                'requestedByEmployee.position',
                'approvals.approverEmployee',
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        return JobRequisitionResource::collection($requisitions);
    }

    /**
     * Approve a job requisition.
     */
    public function approve(
        ApproveJobRequisitionRequest $request,
        JobRequisition $jobRequisition
    ): JobRequisitionResource {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $requisition = $this->jobRequisitionService->approve(
            $jobRequisition,
            $employee,
            $request->validated('remarks')
        );

        return new JobRequisitionResource($requisition);
    }

    /**
     * Reject a job requisition.
     */
    public function reject(
        RejectJobRequisitionRequest $request,
        JobRequisition $jobRequisition
    ): JobRequisitionResource {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $requisition = $this->jobRequisitionService->reject(
            $jobRequisition,
            $employee,
            $request->validated('reason')
        );

        return new JobRequisitionResource($requisition);
    }
}
