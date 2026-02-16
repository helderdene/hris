<?php

namespace App\Http\Controllers\Overtime;

use App\Enums\OvertimeRequestStatus;
use App\Enums\OvertimeType;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeRequestPageController extends Controller
{
    /**
     * Display the overtime requests list for HR/admin.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $overtimeType = $request->input('overtime_type');
        $employeeId = $request->input('employee_id');

        $query = OvertimeRequest::query()
            ->with(['employee.department', 'employee.position', 'approvals'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($overtimeType) {
            $query->where('overtime_type', $overtimeType);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $requests = $query->paginate(25)->through(fn ($req) => [
            'id' => $req->id,
            'reference_number' => $req->reference_number,
            'employee' => [
                'id' => $req->employee->id,
                'full_name' => $req->employee->full_name,
                'employee_number' => $req->employee->employee_number,
                'department' => $req->employee->department?->name,
                'position' => $req->employee->position?->name,
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
            'created_at' => $req->created_at->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('Overtime/Requests/Index', [
            'requests' => $requests,
            'statuses' => OvertimeRequestStatus::options(),
            'overtimeTypes' => OvertimeType::options(),
            'filters' => [
                'status' => $status,
                'overtime_type' => $overtimeType,
                'search' => $request->input('search'),
            ],
        ]);
    }

    /**
     * Display a specific overtime request.
     */
    public function show(OvertimeRequest $overtimeRequest): Response
    {
        $overtimeRequest->load([
            'employee.department',
            'employee.position',
            'approvals.approverEmployee',
            'dailyTimeRecord',
        ]);

        return Inertia::render('Overtime/Requests/Show', [
            'request' => [
                'id' => $overtimeRequest->id,
                'reference_number' => $overtimeRequest->reference_number,
                'employee' => [
                    'id' => $overtimeRequest->employee->id,
                    'full_name' => $overtimeRequest->employee->full_name,
                    'employee_number' => $overtimeRequest->employee->employee_number,
                    'department' => $overtimeRequest->employee->department?->name,
                    'position' => $overtimeRequest->employee->position?->name,
                ],
                'overtime_date' => $overtimeRequest->overtime_date->format('Y-m-d'),
                'expected_start_time' => $overtimeRequest->expected_start_time,
                'expected_end_time' => $overtimeRequest->expected_end_time,
                'expected_minutes' => $overtimeRequest->expected_minutes,
                'expected_hours_formatted' => $overtimeRequest->expected_hours_formatted,
                'overtime_type' => $overtimeRequest->overtime_type->value,
                'overtime_type_label' => $overtimeRequest->overtime_type->label(),
                'overtime_type_color' => $overtimeRequest->overtime_type->color(),
                'reason' => $overtimeRequest->reason,
                'status' => $overtimeRequest->status->value,
                'status_label' => $overtimeRequest->status->label(),
                'status_color' => $overtimeRequest->status->color(),
                'current_approval_level' => $overtimeRequest->current_approval_level,
                'total_approval_levels' => $overtimeRequest->total_approval_levels,
                'approvals' => $overtimeRequest->approvals->map(fn ($approval) => [
                    'id' => $approval->id,
                    'approval_level' => $approval->approval_level,
                    'approver_type' => $approval->approver_type,
                    'approver_name' => $approval->approver_name,
                    'approver_position' => $approval->approver_position,
                    'decision' => $approval->decision->value,
                    'decision_label' => $approval->decision->label(),
                    'decision_color' => $approval->decision->color(),
                    'remarks' => $approval->remarks,
                    'decided_at' => $approval->decided_at?->format('Y-m-d H:i:s'),
                ]),
                'daily_time_record_id' => $overtimeRequest->daily_time_record_id,
                'cancellation_reason' => $overtimeRequest->cancellation_reason,
                'submitted_at' => $overtimeRequest->submitted_at?->format('Y-m-d H:i:s'),
                'approved_at' => $overtimeRequest->approved_at?->format('Y-m-d H:i:s'),
                'rejected_at' => $overtimeRequest->rejected_at?->format('Y-m-d H:i:s'),
                'cancelled_at' => $overtimeRequest->cancelled_at?->format('Y-m-d H:i:s'),
                'created_at' => $overtimeRequest->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $overtimeRequest->can_be_edited,
                'can_be_cancelled' => $overtimeRequest->can_be_cancelled,
            ],
        ]);
    }
}
