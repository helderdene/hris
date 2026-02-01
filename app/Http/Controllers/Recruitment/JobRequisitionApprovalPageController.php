<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\JobRequisitionStatus;
use App\Enums\LeaveApprovalDecision;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\JobRequisition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobRequisitionApprovalPageController extends Controller
{
    /**
     * Display the pending job requisition approvals page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $pendingCount = 0;
        $approvedToday = 0;
        $rejectedToday = 0;
        $pendingRequisitions = [];
        $historyRequisitions = [];

        if ($employee) {
            $pendingRequisitions = JobRequisition::query()
                ->where('status', JobRequisitionStatus::Pending)
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', LeaveApprovalDecision::Pending)
                        ->whereColumn('approval_level', 'job_requisitions.current_approval_level');
                })
                ->with(['position', 'department', 'requestedByEmployee.department', 'requestedByEmployee.position'])
                ->orderBy('submitted_at', 'asc')
                ->get()
                ->map(fn ($req) => [
                    'id' => $req->id,
                    'reference_number' => $req->reference_number,
                    'position' => [
                        'id' => $req->position->id,
                        'name' => $req->position->title,
                    ],
                    'department' => [
                        'id' => $req->department->id,
                        'name' => $req->department->name,
                    ],
                    'requested_by' => [
                        'id' => $req->requestedByEmployee->id,
                        'full_name' => $req->requestedByEmployee->full_name,
                        'employee_number' => $req->requestedByEmployee->employee_number,
                        'department' => $req->requestedByEmployee->department?->name,
                        'position' => $req->requestedByEmployee->position?->title,
                    ],
                    'headcount' => $req->headcount,
                    'employment_type_label' => $req->employment_type->label(),
                    'urgency' => $req->urgency->value,
                    'urgency_label' => $req->urgency->label(),
                    'urgency_color' => $req->urgency->color(),
                    'justification' => $req->justification,
                    'status' => $req->status->value,
                    'status_label' => $req->status->label(),
                    'status_color' => $req->status->color(),
                    'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
                    'current_approval_level' => $req->current_approval_level,
                    'total_approval_levels' => $req->total_approval_levels,
                ]);

            $pendingCount = $pendingRequisitions->count();

            $historyPaginator = JobRequisition::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->whereIn('decision', [LeaveApprovalDecision::Approved, LeaveApprovalDecision::Rejected]);
                })
                ->with(['position', 'department', 'requestedByEmployee.department', 'requestedByEmployee.position'])
                ->orderByDesc('updated_at')
                ->paginate(15, ['*'], 'history_page');

            $historyRequisitions = $historyPaginator->through(fn ($req) => [
                'id' => $req->id,
                'reference_number' => $req->reference_number,
                'position' => [
                    'id' => $req->position->id,
                    'name' => $req->position->title,
                ],
                'department' => [
                    'id' => $req->department->id,
                    'name' => $req->department->name,
                ],
                'requested_by' => [
                    'id' => $req->requestedByEmployee->id,
                    'full_name' => $req->requestedByEmployee->full_name,
                    'employee_number' => $req->requestedByEmployee->employee_number,
                    'department' => $req->requestedByEmployee->department?->name,
                    'position' => $req->requestedByEmployee->position?->title,
                ],
                'headcount' => $req->headcount,
                'employment_type_label' => $req->employment_type->label(),
                'urgency' => $req->urgency->value,
                'urgency_label' => $req->urgency->label(),
                'urgency_color' => $req->urgency->color(),
                'status' => $req->status->value,
                'status_label' => $req->status->label(),
                'status_color' => $req->status->color(),
                'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
                'current_approval_level' => $req->current_approval_level,
                'total_approval_levels' => $req->total_approval_levels,
            ]);

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
        }

        return Inertia::render('Recruitment/Approvals/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'pendingRequisitions' => $pendingRequisitions,
            'historyRequisitions' => $historyRequisitions,
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
