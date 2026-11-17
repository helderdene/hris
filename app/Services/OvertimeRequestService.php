<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeRequestStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRequestApproval;
use App\Notifications\OvertimeRequestApproved;
use App\Notifications\OvertimeRequestRejected;
use App\Notifications\OvertimeRequestSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing overtime request workflow.
 *
 * Handles submission, approval, rejection, cancellation, and DTR linking.
 */
class OvertimeRequestService
{
    public function __construct(
        protected ApprovalChainResolver $chainResolver
    ) {}

    /**
     * Submit an overtime request for approval.
     *
     * @throws ValidationException
     */
    public function submit(OvertimeRequest $request): OvertimeRequest
    {
        if ($request->status !== OvertimeRequestStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft requests can be submitted.',
            ]);
        }

        return DB::transaction(function () use ($request) {
            $approvers = $this->resolveApprovers($request->employee);

            if ($approvers->isEmpty()) {
                throw ValidationException::withMessages([
                    'approver' => 'No approver found. Please contact HR.',
                ]);
            }

            foreach ($approvers as $approverData) {
                OvertimeRequestApproval::create([
                    'overtime_request_id' => $request->id,
                    'approval_level' => $approverData['level'],
                    'approver_type' => $approverData['type'],
                    'approver_employee_id' => $approverData['employee']->id,
                    'approver_name' => $approverData['employee']->full_name,
                    'approver_position' => $approverData['employee']->position?->name,
                    'decision' => OvertimeApprovalDecision::Pending,
                ]);
            }

            $request->status = OvertimeRequestStatus::Pending;
            $request->current_approval_level = 1;
            $request->total_approval_levels = $approvers->max('level');
            $request->submitted_at = now();
            $request->metadata = array_merge($request->metadata ?? [], [
                'submitted_by' => auth()->id(),
                'submitted_ip' => request()->ip(),
            ]);
            $request->save();

            $this->notifyApproversAtLevel($request, 1);

            if (tenant()?->overtimeLevelTwoCanSkip() && $request->total_approval_levels > 1) {
                $this->notifyApproversAtLevel($request, 2);
            }

            return $request->fresh(['approvals', 'employee']);
        });
    }

    /**
     * Approve an overtime request at the current level.
     *
     * @throws ValidationException
     */
    public function approve(
        OvertimeRequest $request,
        Employee $approver,
        ?string $remarks = null
    ): OvertimeRequest {
        if ($request->status !== OvertimeRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be approved.',
            ]);
        }

        $approval = $this->findActionableApproval($request, $approver);

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to approve this request at this level.',
            ]);
        }

        return DB::transaction(function () use ($request, $approval, $remarks) {
            $approval->approve($remarks);
            $this->skipPendingApprovals($request, "Approved by {$approval->approver_name}", $approval->approval_level);
            $request->current_approval_level = $approval->approval_level;

            if ($request->current_approval_level >= $request->total_approval_levels) {
                $request->status = OvertimeRequestStatus::Approved;
                $request->approved_at = now();
                $request->save();

                $this->linkToDtr($request);

                $request->employee->user?->notify(new OvertimeRequestApproved($request));
            } else {
                $request->current_approval_level++;
                $request->save();

                $this->notifyApproversAtLevel($request, $request->current_approval_level);
            }

            return $request->fresh(['approvals', 'employee']);
        });
    }

    /**
     * Reject an overtime request.
     *
     * @throws ValidationException
     */
    public function reject(
        OvertimeRequest $request,
        Employee $approver,
        string $reason
    ): OvertimeRequest {
        if ($request->status !== OvertimeRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be rejected.',
            ]);
        }

        $approval = $this->findActionableApproval($request, $approver);

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to reject this request.',
            ]);
        }

        return DB::transaction(function () use ($request, $approval, $reason) {
            $approval->reject($reason);

            $this->skipPendingApprovals($request, 'Request rejected at an earlier level');

            $request->status = OvertimeRequestStatus::Rejected;
            $request->rejected_at = now();
            $request->save();

            $request->employee->user?->notify(new OvertimeRequestRejected($request, $reason));

            return $request->fresh(['approvals', 'employee']);
        });
    }

    /**
     * Cancel an overtime request.
     *
     * @throws ValidationException
     */
    public function cancel(
        OvertimeRequest $request,
        ?string $reason = null
    ): OvertimeRequest {
        if (! $request->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This request cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($request, $reason) {
            $this->skipPendingApprovals($request, 'Request cancelled');

            $request->status = OvertimeRequestStatus::Cancelled;
            $request->cancelled_at = now();
            $request->cancellation_reason = $reason;
            $request->save();

            return $request->fresh(['approvals', 'employee']);
        });
    }

    /**
     * Resolve the approval levels for an employee.
     *
     * Level 1 is the Department Head. Level 2 is every flagged overtime
     * approver (any one of them may decide), falling back to the tenant
     * Admin Manager when no overtime approver is configured.
     *
     * @return Collection<int, array{employee: Employee, type: string, level: int}>
     */
    public function resolveApprovers(Employee $employee): Collection
    {
        $departmentHead = $this->chainResolver->resolveDepartmentHead($employee);

        $levelTwoApprovers = Employee::query()
            ->where('is_overtime_approver', true)
            ->where('employment_status', EmploymentStatus::Active)
            ->orderBy('id')
            ->get();
        $levelTwoType = 'overtime_approver';

        if ($levelTwoApprovers->isEmpty()) {
            $levelTwoApprovers = collect([$this->chainResolver->resolveAdminManager($employee)])->filter();
            $levelTwoType = 'admin_manager';
        }

        $levelTwoApprovers = $levelTwoApprovers
            ->reject(fn (Employee $approver) => in_array($approver->id, [$employee->id, $departmentHead?->id], true));

        return collect([
            $departmentHead ? collect([['employee' => $departmentHead, 'type' => 'department_head']]) : collect(),
            $levelTwoApprovers->map(fn (Employee $approver) => ['employee' => $approver, 'type' => $levelTwoType]),
        ])
            ->filter(fn (Collection $approvers) => $approvers->isNotEmpty())
            ->values()
            ->flatMap(fn (Collection $approvers, int $index) => $approvers->map(fn (array $approver) => [
                ...$approver,
                'level' => $index + 1,
            ]))
            ->values();
    }

    /**
     * Find the approver's pending approval they may act on now: at the current
     * level, or at a later level when the tenant lets level 2 skip level 1.
     */
    protected function findActionableApproval(OvertimeRequest $request, Employee $approver): ?OvertimeRequestApproval
    {
        return $request->approvals()
            ->where('approval_level', tenant()?->overtimeLevelTwoCanSkip() ? '>=' : '=', $request->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', OvertimeApprovalDecision::Pending)
            ->orderBy('approval_level')
            ->first();
    }

    /**
     * Notify every approver assigned to the given level.
     */
    protected function notifyApproversAtLevel(OvertimeRequest $request, int $level): void
    {
        $request->approvals()
            ->where('approval_level', $level)
            ->with('approverEmployee.user')
            ->get()
            ->each(fn (OvertimeRequestApproval $approval) => $approval->approverEmployee?->user?->notify(new OvertimeRequestSubmitted($request)));
    }

    /**
     * Mark still-pending approvals (optionally only up to one level) as skipped
     * so they no longer surface as pending actions or priority alerts.
     */
    protected function skipPendingApprovals(OvertimeRequest $request, string $reason, ?int $level = null): void
    {
        $request->approvals()
            ->where('decision', OvertimeApprovalDecision::Pending)
            ->when($level !== null, fn ($query) => $query->where('approval_level', '<=', $level))
            ->update([
                'decision' => OvertimeApprovalDecision::Skipped,
                'remarks' => $reason,
                'decided_at' => now(),
            ]);
    }

    /**
     * Link an approved overtime request to the matching DTR.
     */
    public function linkToDtr(OvertimeRequest $request): void
    {
        $dtr = DailyTimeRecord::query()
            ->where('employee_id', $request->employee_id)
            ->where('date', $request->overtime_date)
            ->first();

        if ($dtr) {
            $dtr->overtime_approved = true;
            $dtr->overtime_denied = false;
            $dtr->overtime_request_id = $request->id;
            $dtr->save();

            $request->daily_time_record_id = $dtr->id;
            $request->save();
        }
    }
}
