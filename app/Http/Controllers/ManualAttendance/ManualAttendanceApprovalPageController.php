<?php

namespace App\Http\Controllers\ManualAttendance;

use App\Enums\ManualAttendanceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\ManualAttendanceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualAttendanceApprovalPageController extends Controller
{
    /**
     * Display the manual attendance approval queue for the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::query()->where('user_id', $user->id)->first();

        $pendingRequests = collect();
        $historyRequests = collect();
        $pendingCount = 0;
        $approvedToday = 0;
        $rejectedToday = 0;

        if ($employee) {
            $pendingRequests = ManualAttendanceRequest::query()
                ->forApprover($employee)
                ->with(['employee.department', 'employee.position'])
                ->orderBy('submitted_at')
                ->get()
                ->map(fn (ManualAttendanceRequest $req) => $this->mapPending($req));

            $pendingCount = $pendingRequests->count();

            $historyRequests = ManualAttendanceRequest::query()
                ->where('decided_by_user_id', $user->id)
                ->whereIn('status', [
                    ManualAttendanceRequestStatus::Approved,
                    ManualAttendanceRequestStatus::Rejected,
                ])
                ->with(['employee.department', 'employee.position'])
                ->orderByDesc('decided_at')
                ->limit(50)
                ->get()
                ->map(fn (ManualAttendanceRequest $req) => $this->mapHistory($req));

            $approvedToday = ManualAttendanceRequest::query()
                ->where('decided_by_user_id', $user->id)
                ->where('status', ManualAttendanceRequestStatus::Approved)
                ->whereDate('decided_at', today())
                ->count();

            $rejectedToday = ManualAttendanceRequest::query()
                ->where('decided_by_user_id', $user->id)
                ->where('status', ManualAttendanceRequestStatus::Rejected)
                ->whereDate('decided_at', today())
                ->count();
        }

        return Inertia::render('ManualAttendance/Approvals/Index', [
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

    /**
     * Map a pending request to the payload shape used by the page.
     *
     * Includes existing AttendanceLog rows for the requested date so the
     * approver can see conflicts at a glance.
     *
     * @return array<string, mixed>
     */
    protected function mapPending(ManualAttendanceRequest $req): array
    {
        $existingLogs = AttendanceLog::query()
            ->where('employee_id', $req->employee_id)
            ->whereDate('logged_at', $req->attendance_date)
            ->orderBy('logged_at')
            ->get()
            ->map(fn (AttendanceLog $log) => [
                'id' => $log->id,
                'logged_at' => $log->logged_at?->format('Y-m-d H:i:s'),
                'direction' => $log->direction,
                'source' => $log->source?->value,
            ])
            ->all();

        return array_merge($this->mapCommon($req), [
            'existing_logs' => $existingLogs,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapHistory(ManualAttendanceRequest $req): array
    {
        return array_merge($this->mapCommon($req), [
            'decided_at' => $req->decided_at?->format('Y-m-d H:i:s'),
            'decided_by_role' => $req->decided_by_role,
            'decision_remarks' => $req->decision_remarks,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapCommon(ManualAttendanceRequest $req): array
    {
        return [
            'id' => $req->id,
            'reference_number' => $req->reference_number,
            'employee' => [
                'id' => $req->employee->id,
                'full_name' => $req->employee->full_name,
                'employee_number' => $req->employee->employee_number,
                'department' => $req->employee->department?->name,
                'position' => $req->employee->position?->title,
            ],
            'attendance_date' => $req->attendance_date->format('Y-m-d'),
            'time_in' => $req->time_in,
            'time_out' => $req->time_out,
            'reason' => $req->reason,
            'status' => $req->status->value,
            'status_label' => $req->status->label(),
            'status_color' => $req->status->color(),
            'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
