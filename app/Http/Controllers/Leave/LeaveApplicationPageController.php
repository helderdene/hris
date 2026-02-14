<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveApplicationPageController extends Controller
{
    /**
     * Display the leave applications index page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $year = (int) $request->input('year', now()->year);
        $status = $request->input('status');

        // Get leave types for the dropdown
        $leaveTypes = LeaveType::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'requires_attachment' => $type->requires_attachment,
                'min_days_advance_notice' => $type->min_days_advance_notice,
            ]);

        // Get employee's leave balances for the current year
        $balances = [];
        $applications = [];

        if ($employee) {
            $balances = $employee->leaveBalances()
                ->where('year', now()->year)
                ->with('leaveType')
                ->get()
                ->map(fn ($balance) => [
                    'leave_type_id' => $balance->leave_type_id,
                    'leave_type_name' => $balance->leaveType->name,
                    'available' => $balance->available,
                    'used' => (float) $balance->used,
                    'pending' => (float) $balance->pending,
                ]);

            // Get employee's leave applications
            $query = LeaveApplication::query()
                ->where('employee_id', $employee->id)
                ->whereYear('start_date', $year)
                ->with(['leaveType', 'approvals'])
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $applications = $query->get()->map(fn ($app) => [
                'id' => $app->id,
                'reference_number' => $app->reference_number,
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
                'created_at' => $app->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $app->can_be_edited,
                'can_be_cancelled' => $app->can_be_cancelled,
            ]);
        }

        return Inertia::render('Leave/Applications/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ] : null,
            'leaveTypes' => $leaveTypes,
            'balances' => $balances,
            'applications' => $applications,
            'statuses' => LeaveApplicationStatus::options(),
            'filters' => [
                'status' => $status,
                'year' => $year,
            ],
        ]);
    }

    /**
     * Display a specific leave application.
     */
    public function show(LeaveApplication $leaveApplication): Response
    {
        $leaveApplication->load([
            'employee.department',
            'employee.position',
            'leaveType',
            'leaveBalance',
            'approvals.approverEmployee',
        ]);

        return Inertia::render('Leave/Applications/Show', [
            'application' => [
                'id' => $leaveApplication->id,
                'reference_number' => $leaveApplication->reference_number,
                'employee' => [
                    'id' => $leaveApplication->employee->id,
                    'full_name' => $leaveApplication->employee->full_name,
                    'employee_number' => $leaveApplication->employee->employee_number,
                    'department' => $leaveApplication->employee->department?->name,
                    'position' => $leaveApplication->employee->position?->name,
                ],
                'leave_type' => [
                    'id' => $leaveApplication->leaveType->id,
                    'name' => $leaveApplication->leaveType->name,
                    'code' => $leaveApplication->leaveType->code,
                ],
                'start_date' => $leaveApplication->start_date->format('Y-m-d'),
                'end_date' => $leaveApplication->end_date->format('Y-m-d'),
                'date_range' => $leaveApplication->date_range,
                'total_days' => (float) $leaveApplication->total_days,
                'is_half_day_start' => $leaveApplication->is_half_day_start,
                'is_half_day_end' => $leaveApplication->is_half_day_end,
                'reason' => $leaveApplication->reason,
                'status' => $leaveApplication->status->value,
                'status_label' => $leaveApplication->status->label(),
                'status_color' => $leaveApplication->status->color(),
                'current_approval_level' => $leaveApplication->current_approval_level,
                'total_approval_levels' => $leaveApplication->total_approval_levels,
                'approvals' => $leaveApplication->approvals->map(fn ($approval) => [
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
                'balance' => $leaveApplication->leaveBalance ? [
                    'available' => $leaveApplication->leaveBalance->available,
                    'used' => (float) $leaveApplication->leaveBalance->used,
                    'pending' => (float) $leaveApplication->leaveBalance->pending,
                ] : null,
                'cancellation_reason' => $leaveApplication->cancellation_reason,
                'submitted_at' => $leaveApplication->submitted_at?->format('Y-m-d H:i:s'),
                'approved_at' => $leaveApplication->approved_at?->format('Y-m-d H:i:s'),
                'rejected_at' => $leaveApplication->rejected_at?->format('Y-m-d H:i:s'),
                'cancelled_at' => $leaveApplication->cancelled_at?->format('Y-m-d H:i:s'),
                'created_at' => $leaveApplication->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $leaveApplication->can_be_edited,
                'can_be_cancelled' => $leaveApplication->can_be_cancelled,
            ],
        ]);
    }
}
