<?php

namespace App\Http\Controllers\My;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveApprovalDecision;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyLeaveApprovalController extends Controller
{
    /**
     * Display the manager's leave approval queue.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $pendingCount = 0;
        $approvedToday = 0;
        $rejectedToday = 0;
        $pendingApplications = [];
        $historyApplications = [];

        if ($employee) {
            $pendingApplications = LeaveApplication::query()
                ->where('status', LeaveApplicationStatus::Pending)
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', LeaveApprovalDecision::Pending)
                        ->whereColumn('approval_level', 'leave_applications.current_approval_level');
                })
                ->with(['employee.department', 'employee.position', 'leaveType'])
                ->orderBy('submitted_at', 'asc')
                ->get()
                ->map(fn ($app) => [
                    'id' => $app->id,
                    'reference_number' => $app->reference_number,
                    'employee' => [
                        'id' => $app->employee->id,
                        'full_name' => $app->employee->full_name,
                        'employee_number' => $app->employee->employee_number,
                        'department' => $app->employee->department?->name,
                        'position' => $app->employee->position?->title,
                    ],
                    'leave_type' => [
                        'id' => $app->leaveType->id,
                        'name' => $app->leaveType->name,
                        'code' => $app->leaveType->code,
                    ],
                    'start_date' => $app->start_date->format('Y-m-d'),
                    'end_date' => $app->end_date->format('Y-m-d'),
                    'date_range' => $app->date_range,
                    'total_days' => (float) $app->total_days,
                    'reason' => $app->reason,
                    'status' => $app->status->value,
                    'status_label' => $app->status->label(),
                    'status_color' => $app->status->color(),
                    'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
                    'current_approval_level' => $app->current_approval_level,
                    'total_approval_levels' => $app->total_approval_levels,
                ]);

            $pendingCount = $pendingApplications->count();

            $historyPaginator = LeaveApplication::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->whereIn('decision', [LeaveApprovalDecision::Approved, LeaveApprovalDecision::Rejected]);
                })
                ->with(['employee.department', 'employee.position', 'leaveType'])
                ->orderByDesc('updated_at')
                ->paginate(15, ['*'], 'history_page');

            $historyApplications = $historyPaginator->through(fn ($app) => [
                'id' => $app->id,
                'reference_number' => $app->reference_number,
                'employee' => [
                    'id' => $app->employee->id,
                    'full_name' => $app->employee->full_name,
                    'employee_number' => $app->employee->employee_number,
                    'department' => $app->employee->department?->name,
                    'position' => $app->employee->position?->title,
                ],
                'leave_type' => [
                    'id' => $app->leaveType->id,
                    'name' => $app->leaveType->name,
                    'code' => $app->leaveType->code,
                ],
                'start_date' => $app->start_date->format('Y-m-d'),
                'end_date' => $app->end_date->format('Y-m-d'),
                'date_range' => $app->date_range,
                'total_days' => (float) $app->total_days,
                'reason' => $app->reason,
                'status' => $app->status->value,
                'status_label' => $app->status->label(),
                'status_color' => $app->status->color(),
                'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
                'current_approval_level' => $app->current_approval_level,
                'total_approval_levels' => $app->total_approval_levels,
            ]);

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
        }

        return Inertia::render('My/LeaveApprovals/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'pendingApplications' => $pendingApplications,
            'historyApplications' => $historyApplications,
            'summary' => [
                'pending_count' => $pendingCount,
                'approved_today' => $approvedToday,
                'rejected_today' => $rejectedToday,
            ],
            'filters' => [
                'tab' => $request->input('tab', 'pending'),
            ],
        ]);
    }
}
