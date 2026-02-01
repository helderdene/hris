<?php

namespace App\Http\Controllers\My;

use App\Enums\LeaveApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyLeaveController extends Controller
{
    /**
     * Display the employee's own leave applications and balances.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $year = (int) $request->input('year', now()->year);
        $status = $request->input('status');

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

            $query = $employee->leaveApplications()
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
                'is_half_day_start' => $app->is_half_day_start,
                'is_half_day_end' => $app->is_half_day_end,
            ]);
        }

        return Inertia::render('My/Leave/Index', [
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
}
