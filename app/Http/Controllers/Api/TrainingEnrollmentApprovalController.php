<?php

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveTrainingEnrollmentRequest;
use App\Http\Requests\RejectTrainingEnrollmentRequest;
use App\Http\Resources\TrainingEnrollmentRequestResource;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Services\Training\TrainingEnrollmentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrainingEnrollmentApprovalController extends Controller
{
    public function __construct(
        protected TrainingEnrollmentRequestService $enrollmentRequestService
    ) {}

    /**
     * Get training enrollments pending the current user's approval.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return TrainingEnrollmentRequestResource::collection(collect());
        }

        $enrollments = $this->enrollmentRequestService->getPendingForApprover($employee);

        return TrainingEnrollmentRequestResource::collection($enrollments);
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

        $pendingCount = TrainingEnrollment::query()
            ->pendingForApprover($employee)
            ->count();

        $approvedToday = TrainingEnrollment::query()
            ->where('approver_employee_id', $employee->id)
            ->whereNotNull('approved_at')
            ->whereDate('approved_at', today())
            ->count();

        $rejectedToday = TrainingEnrollment::query()
            ->where('approver_employee_id', $employee->id)
            ->whereNotNull('rejected_at')
            ->whereDate('rejected_at', today())
            ->count();

        return response()->json([
            'pending_count' => $pendingCount,
            'approved_today' => $approvedToday,
            'rejected_today' => $rejectedToday,
        ]);
    }

    /**
     * Approve a training enrollment request.
     */
    public function approve(
        ApproveTrainingEnrollmentRequest $request,
        TrainingEnrollment $enrollment
    ): TrainingEnrollmentRequestResource {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $enrollment = $this->enrollmentRequestService->approve(
            $enrollment,
            $employee,
            $request->validated('remarks')
        );

        return new TrainingEnrollmentRequestResource($enrollment);
    }

    /**
     * Reject a training enrollment request.
     */
    public function reject(
        RejectTrainingEnrollmentRequest $request,
        TrainingEnrollment $enrollment
    ): TrainingEnrollmentRequestResource {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $enrollment = $this->enrollmentRequestService->reject(
            $enrollment,
            $employee,
            $request->validated('reason')
        );

        return new TrainingEnrollmentRequestResource($enrollment);
    }

    /**
     * Get approval history for the current user.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return TrainingEnrollmentRequestResource::collection(collect());
        }

        $enrollments = TrainingEnrollment::query()
            ->where('approver_employee_id', $employee->id)
            ->whereIn('status', [
                EnrollmentStatus::Confirmed->value,
                EnrollmentStatus::Rejected->value,
            ])
            ->with(['session.course', 'employee.position', 'employee.department'])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        return TrainingEnrollmentRequestResource::collection($enrollments);
    }
}
