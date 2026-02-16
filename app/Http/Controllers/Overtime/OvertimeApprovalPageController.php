<?php

namespace App\Http\Controllers\Overtime;

use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeApprovalPageController extends Controller
{
    /**
     * Display the overtime approval queue for the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $pendingCount = 0;
        $approvedToday = 0;
        $rejectedToday = 0;
        $pendingRequests = [];
        $historyRequests = [];

        if ($employee) {
            $pendingRequests = OvertimeRequest::query()
                ->where('status', OvertimeRequestStatus::Pending)
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', OvertimeApprovalDecision::Pending)
                        ->whereColumn('approval_level', 'overtime_requests.current_approval_level');
                })
                ->with(['employee.department', 'employee.position'])
                ->orderBy('submitted_at', 'asc')
                ->get()
                ->map(fn ($req) => [
                    'id' => $req->id,
                    'reference_number' => $req->reference_number,
                    'employee' => [
                        'id' => $req->employee->id,
                        'full_name' => $req->employee->full_name,
                        'employee_number' => $req->employee->employee_number,
                        'department' => $req->employee->department?->name,
                        'position' => $req->employee->position?->title,
                    ],
                    'overtime_date' => $req->overtime_date->format('Y-m-d'),
                    'expected_minutes' => $req->expected_minutes,
                    'expected_hours_formatted' => $req->expected_hours_formatted,
                    'overtime_type' => $req->overtime_type->value,
                    'overtime_type_label' => $req->overtime_type->label(),
                    'overtime_type_color' => $req->overtime_type->color(),
                    'reason' => $req->reason,
                    'status' => $req->status->value,
                    'status_label' => $req->status->label(),
                    'status_color' => $req->status->color(),
                    'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
                    'current_approval_level' => $req->current_approval_level,
                    'total_approval_levels' => $req->total_approval_levels,
                ]);

            $pendingCount = $pendingRequests->count();

            $historyPaginator = OvertimeRequest::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->whereIn('decision', [OvertimeApprovalDecision::Approved, OvertimeApprovalDecision::Rejected]);
                })
                ->with(['employee.department', 'employee.position'])
                ->orderByDesc('updated_at')
                ->paginate(15, ['*'], 'history_page');

            $historyRequests = $historyPaginator->through(fn ($req) => [
                'id' => $req->id,
                'reference_number' => $req->reference_number,
                'employee' => [
                    'id' => $req->employee->id,
                    'full_name' => $req->employee->full_name,
                    'employee_number' => $req->employee->employee_number,
                    'department' => $req->employee->department?->name,
                    'position' => $req->employee->position?->title,
                ],
                'overtime_date' => $req->overtime_date->format('Y-m-d'),
                'expected_minutes' => $req->expected_minutes,
                'expected_hours_formatted' => $req->expected_hours_formatted,
                'overtime_type' => $req->overtime_type->value,
                'overtime_type_label' => $req->overtime_type->label(),
                'overtime_type_color' => $req->overtime_type->color(),
                'reason' => $req->reason,
                'status' => $req->status->value,
                'status_label' => $req->status->label(),
                'status_color' => $req->status->color(),
                'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
                'current_approval_level' => $req->current_approval_level,
                'total_approval_levels' => $req->total_approval_levels,
            ]);

            $approvedToday = OvertimeRequest::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', OvertimeApprovalDecision::Approved)
                        ->whereDate('decided_at', today());
                })
                ->count();

            $rejectedToday = OvertimeRequest::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', OvertimeApprovalDecision::Rejected)
                        ->whereDate('decided_at', today());
                })
                ->count();
        }

        return Inertia::render('Overtime/Approvals/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'pendingRequests' => $pendingRequests,
            'historyRequests' => $historyRequests,
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
