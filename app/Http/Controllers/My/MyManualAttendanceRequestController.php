<?php

namespace App\Http\Controllers\My;

use App\Enums\ManualAttendanceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyManualAttendanceRequestController extends Controller
{
    /**
     * Display the employee's own manual attendance requests.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::query()->where('user_id', $user->id)->first();

        $year = (int) $request->input('year', now()->year);
        $status = $request->input('status');

        $requests = [];

        if ($employee) {
            $query = $employee->manualAttendanceRequests()
                ->whereYear('attendance_date', $year)
                ->orderByDesc('created_at');

            if ($status) {
                $query->where('status', $status);
            }

            $requests = $query->get()->map(fn ($req) => [
                'id' => $req->id,
                'reference_number' => $req->reference_number,
                'attendance_date' => $req->attendance_date->format('Y-m-d'),
                'time_in' => $req->time_in,
                'time_out' => $req->time_out,
                'reason' => $req->reason,
                'status' => $req->status->value,
                'status_label' => $req->status->label(),
                'status_color' => $req->status->color(),
                'decided_by_role' => $req->decided_by_role,
                'decision_remarks' => $req->decision_remarks,
                'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
                'decided_at' => $req->decided_at?->format('Y-m-d H:i:s'),
                'created_at' => $req->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $req->can_be_edited,
                'can_be_cancelled' => $req->can_be_cancelled,
            ]);
        }

        return Inertia::render('My/ManualAttendanceRequests', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ] : null,
            'requests' => $requests,
            'statuses' => ManualAttendanceRequestStatus::options(),
            'filters' => [
                'status' => $status,
                'year' => $year,
            ],
        ]);
    }
}
