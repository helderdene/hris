<?php

namespace App\Http\Controllers\My;

use App\Enums\OvertimeRequestStatus;
use App\Enums\OvertimeType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyOvertimeRequestController extends Controller
{
    /**
     * Display the employee's own overtime requests.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $year = (int) $request->input('year', now()->year);
        $status = $request->input('status');

        $requests = [];

        if ($employee) {
            $query = $employee->overtimeRequests()
                ->whereYear('overtime_date', $year)
                ->with(['approvals'])
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $requests = $query->get()->map(fn ($req) => [
                'id' => $req->id,
                'reference_number' => $req->reference_number,
                'overtime_date' => $req->overtime_date->format('Y-m-d'),
                'expected_minutes' => $req->expected_minutes,
                'expected_hours_formatted' => $req->expected_hours_formatted,
                'expected_start_time' => $req->expected_start_time,
                'expected_end_time' => $req->expected_end_time,
                'overtime_type' => $req->overtime_type->value,
                'overtime_type_label' => $req->overtime_type->label(),
                'overtime_type_color' => $req->overtime_type->color(),
                'reason' => $req->reason,
                'status' => $req->status->value,
                'status_label' => $req->status->label(),
                'status_color' => $req->status->color(),
                'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
                'created_at' => $req->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $req->can_be_edited,
                'can_be_cancelled' => $req->can_be_cancelled,
            ]);
        }

        return Inertia::render('My/OvertimeRequests', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ] : null,
            'requests' => $requests,
            'statuses' => OvertimeRequestStatus::options(),
            'overtimeTypes' => OvertimeType::options(),
            'filters' => [
                'status' => $status,
                'year' => $year,
            ],
        ]);
    }
}
