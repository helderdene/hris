<?php

namespace App\Http\Controllers\My;

use App\Enums\LeaveApprovalDecision;
use App\Enums\LoanApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Approver-facing loan queue. Shows the pending applications waiting on the
 * current user's decision (CFO / Admin Manager / Releasing) plus the user's
 * decision history.
 */
class MyLoanApprovalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $pendingApplications = collect();
        $historyApplications = collect();
        $summary = [
            'pending_count' => 0,
            'approved_today' => 0,
            'rejected_today' => 0,
        ];

        if ($employee) {
            $pendingApplications = LoanApplication::query()
                ->where('status', LoanApplicationStatus::Pending)
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', LeaveApprovalDecision::Pending)
                        ->whereColumn('approval_level', 'loan_applications.current_approval_level');
                })
                ->with(['employee.department', 'employee.position', 'approvals'])
                ->orderBy('submitted_at', 'asc')
                ->get()
                ->map(fn (LoanApplication $app) => $this->formatApplication($app, $employee->id));

            $summary['pending_count'] = $pendingApplications->count();

            $historyApplications = LoanApplication::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->whereIn('decision', [LeaveApprovalDecision::Approved, LeaveApprovalDecision::Rejected]);
                })
                ->with(['employee.department', 'employee.position', 'approvals'])
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get()
                ->map(fn (LoanApplication $app) => $this->formatApplication($app, $employee->id));

            $summary['approved_today'] = LoanApplication::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', LeaveApprovalDecision::Approved)
                        ->whereDate('decided_at', today());
                })
                ->count();

            $summary['rejected_today'] = LoanApplication::query()
                ->whereHas('approvals', function ($q) use ($employee) {
                    $q->where('approver_employee_id', $employee->id)
                        ->where('decision', LeaveApprovalDecision::Rejected)
                        ->whereDate('decided_at', today());
                })
                ->count();
        }

        return Inertia::render('My/LoanApprovals/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'pendingApplications' => $pendingApplications,
            'historyApplications' => $historyApplications,
            'summary' => $summary,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatApplication(LoanApplication $app, int $approverEmployeeId): array
    {
        $myApproval = $app->approvals->firstWhere('approver_employee_id', $approverEmployeeId);

        return [
            'id' => $app->id,
            'reference_number' => $app->reference_number,
            'employee' => [
                'id' => $app->employee->id,
                'full_name' => $app->employee->full_name,
                'employee_number' => $app->employee->employee_number,
                'department' => $app->employee->department?->name,
                'position' => $app->employee->position?->name,
            ],
            'loan_type' => $app->loan_type->value,
            'loan_type_label' => $app->loan_type->label(),
            'amount_requested' => (float) $app->amount_requested,
            'term_months' => $app->term_months,
            'purpose' => $app->purpose,
            'urgency_level' => $app->urgency_level,
            'status' => $app->status->value,
            'status_label' => $app->status->label(),
            'status_color' => $app->status->color(),
            'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
            'current_approval_level' => $app->current_approval_level,
            'total_approval_levels' => $app->total_approval_levels,
            'sla_deadline_at' => $app->sla_deadline_at?->toIso8601String(),
            'my_approval' => $myApproval ? [
                'id' => $myApproval->id,
                'approval_level' => $myApproval->approval_level,
                'approver_type' => $myApproval->approver_type,
                'decision' => $myApproval->decision->value,
                'decision_label' => $myApproval->decision->label(),
                'remarks' => $myApproval->remarks,
                'decided_at' => $myApproval->decided_at?->format('Y-m-d H:i:s'),
                'deadline_at' => $myApproval->deadline_at?->toIso8601String(),
                'is_overdue' => $myApproval->deadline_at
                    && $myApproval->deadline_at->isPast()
                    && $myApproval->decision->value === 'pending',
            ] : null,
            'is_final_step' => $app->current_approval_level >= max(1, (int) $app->total_approval_levels),
        ];
    }
}
