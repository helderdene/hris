<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveApprovalDecision;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveApprovalPageController extends Controller
{
    /**
     * Display the pending leave approvals page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        // Summary counts
        $pendingCount = 0;
        $approvedToday = 0;
        $rejectedToday = 0;
        $pendingApplications = [];
        $historyApplications = [];

        if ($employee) {
            // Pending applications awaiting this employee's approval
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

            // History - applications this employee has already acted on (paginated)
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

        return Inertia::render('Leave/Approvals/Index', [
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
