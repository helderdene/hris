<?php

namespace App\Http\Controllers\My;

use App\Enums\DocumentRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\DailyTimeRecord;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\PayrollEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SelfServiceDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;
        $tenant = app('tenant');

        $leaveBalances = [];
        $recentPayslips = [];
        $todayDtr = null;
        $announcements = [];
        $recentLeaveApplications = [];
        $documentRequestsSummary = ['pending_count' => 0];
        $loansSummary = ['active_count' => 0, 'total_remaining_balance' => 0];

        if ($employee) {
            $leaveBalances = LeaveBalance::query()
                ->forEmployee($employee->id)
                ->forYear(now()->year)
                ->with('leaveType')
                ->get()
                ->map(fn ($balance) => [
                    'id' => $balance->id,
                    'leave_type' => $balance->leaveType?->name,
                    'available' => $balance->available,
                    'used' => $balance->used,
                ])
                ->toArray();

            $recentLeaveApplications = LeaveApplication::query()
                ->where('employee_id', $employee->id)
                ->with('leaveType')
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn ($app) => [
                    'id' => $app->id,
                    'reference_number' => $app->reference_number,
                    'leave_type' => $app->leaveType?->name,
                    'total_days' => (float) $app->total_days,
                    'status' => $app->status->value,
                    'status_label' => $app->status->label(),
                    'status_color' => $app->status->color(),
                    'start_date' => $app->start_date?->format('M d, Y'),
                ])
                ->toArray();

            $recentPayslips = PayrollEntry::query()
                ->forEmployee($employee->id)
                ->with('payrollPeriod')
                ->latest('computed_at')
                ->limit(3)
                ->get()
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'net_pay' => $entry->net_pay,
                    'period_name' => $entry->payrollPeriod?->name,
                    'period_start' => $entry->payrollPeriod?->start_date?->format('M d, Y'),
                    'period_end' => $entry->payrollPeriod?->end_date?->format('M d, Y'),
                    'status' => $entry->status->value ?? $entry->status,
                ])
                ->toArray();

            $todayDtr = DailyTimeRecord::query()
                ->forEmployee($employee->id)
                ->forDateRange(Carbon::today(), Carbon::today())
                ->first();

            if ($todayDtr) {
                $todayDtr = [
                    'id' => $todayDtr->id,
                    'first_in' => $todayDtr->first_in?->format('h:i A'),
                    'last_out' => $todayDtr->last_out?->format('h:i A'),
                    'status' => $todayDtr->status->value ?? $todayDtr->status,
                    'total_work_hours' => $todayDtr->total_work_hours,
                ];
            }

            $documentRequestsSummary = [
                'pending_count' => DocumentRequest::query()
                    ->forEmployee($employee->id)
                    ->whereIn('status', [DocumentRequestStatus::Pending, DocumentRequestStatus::Processing])
                    ->count(),
            ];

            $activeLoans = EmployeeLoan::query()
                ->forEmployee($employee)
                ->active()
                ->get();

            $loansSummary = [
                'active_count' => $activeLoans->count(),
                'total_remaining_balance' => (float) $activeLoans->sum('remaining_balance'),
            ];
        }

        if ($tenant) {
            $announcements = Announcement::query()
                ->where('tenant_id', $tenant->id)
                ->published()
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->limit(5)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'published_at' => $a->published_at?->format('M d, Y'),
                    'is_pinned' => $a->is_pinned,
                ])
                ->toArray();
        }

        return Inertia::render('My/Dashboard', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'leaveBalances' => $leaveBalances,
            'recentLeaveApplications' => $recentLeaveApplications,
            'recentPayslips' => $recentPayslips,
            'todayDtr' => $todayDtr,
            'announcements' => $announcements,
            'documentRequestsSummary' => $documentRequestsSummary,
            'loansSummary' => $loansSummary,
        ]);
    }
}
