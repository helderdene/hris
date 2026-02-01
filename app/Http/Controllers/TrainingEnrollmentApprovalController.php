<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Resources\TrainingEnrollmentRequestResource;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Services\Training\TrainingEnrollmentRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingEnrollmentApprovalController extends Controller
{
    public function __construct(
        protected TrainingEnrollmentRequestService $enrollmentRequestService
    ) {}

    /**
     * Display the training enrollment approvals page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $pendingEnrollments = collect();
        $recentEnrollments = collect();

        if ($employee) {
            $pendingEnrollments = $this->enrollmentRequestService->getPendingForApprover($employee);

            $recentEnrollments = TrainingEnrollment::query()
                ->where('approver_employee_id', $employee->id)
                ->whereIn('status', [
                    EnrollmentStatus::Confirmed->value,
                    EnrollmentStatus::Rejected->value,
                ])
                ->with(['session.course', 'employee.position', 'employee.department'])
                ->orderBy('updated_at', 'desc')
                ->limit(20)
                ->get();
        }

        return Inertia::render('Training/Approvals/Index', [
            'pendingEnrollments' => TrainingEnrollmentRequestResource::collection($pendingEnrollments),
            'recentEnrollments' => TrainingEnrollmentRequestResource::collection($recentEnrollments),
        ]);
    }
}
