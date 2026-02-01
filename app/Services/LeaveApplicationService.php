<?php

namespace App\Services;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveApprovalDecision;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveBalance;
use App\Notifications\LeaveApplicationApproved;
use App\Notifications\LeaveApplicationCancelled;
use App\Notifications\LeaveApplicationRejected;
use App\Notifications\LeaveApplicationSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing leave application workflow.
 *
 * Handles submission, approval, rejection, and cancellation with
 * balance tracking and notifications.
 */
class LeaveApplicationService
{
    public function __construct(
        protected ApprovalChainResolver $chainResolver
    ) {}

    /**
     * Submit a leave application for approval.
     *
     * - Validates balance availability
     * - Builds approval chain
     * - Reserves balance (pending)
     * - Sends notifications to first approver
     *
     * @throws ValidationException
     */
    public function submit(LeaveApplication $application): LeaveApplication
    {
        // Validate current status allows submission
        if ($application->status !== LeaveApplicationStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft applications can be submitted.',
            ]);
        }

        return DB::transaction(function () use ($application) {
            // Get or create the leave balance for this year
            $balance = $this->getOrCreateBalance($application);

            // Validate sufficient balance
            if (! $balance->hasAvailableBalance((float) $application->total_days)) {
                throw ValidationException::withMessages([
                    'total_days' => 'Insufficient leave balance. Available: '.$balance->available.' days.',
                ]);
            }

            // Build approval chain
            $employee = $application->employee;
            $approvers = $this->chainResolver->resolveChain($employee);

            if ($approvers->isEmpty()) {
                // Try fallback approver
                $fallback = $this->chainResolver->getFallbackApprover($employee);

                if ($fallback) {
                    $approvers = collect([[
                        'employee' => $fallback,
                        'type' => 'fallback',
                        'level' => 1,
                    ]]);
                } else {
                    throw ValidationException::withMessages([
                        'approver' => 'No approver found. Please contact HR.',
                    ]);
                }
            }

            // Create approval records
            foreach ($approvers as $approverData) {
                LeaveApplicationApproval::create([
                    'leave_application_id' => $application->id,
                    'approval_level' => $approverData['level'],
                    'approver_type' => $approverData['type'],
                    'approver_employee_id' => $approverData['employee']->id,
                    'approver_name' => $approverData['employee']->full_name,
                    'approver_position' => $approverData['employee']->position?->name,
                    'decision' => LeaveApprovalDecision::Pending,
                ]);
            }

            // Reserve balance
            $balance->recordPending((float) $application->total_days);

            // Update application status
            $application->leave_balance_id = $balance->id;
            $application->status = LeaveApplicationStatus::Pending;
            $application->current_approval_level = 1;
            $application->total_approval_levels = $approvers->count();
            $application->submitted_at = now();

            // Store submission metadata
            $application->metadata = array_merge($application->metadata ?? [], [
                'submitted_by' => auth()->id(),
                'submitted_ip' => request()->ip(),
            ]);

            $application->save();

            // Notify first approver
            $firstApproval = $application->approvals()->where('approval_level', 1)->first();
            if ($firstApproval && $firstApproval->approverEmployee) {
                $firstApproval->approverEmployee->user?->notify(new LeaveApplicationSubmitted($application));
            }

            return $application->fresh(['approvals', 'leaveType', 'employee']);
        });
    }

    /**
     * Approve a leave application at the current level.
     *
     * @throws ValidationException
     */
    public function approve(
        LeaveApplication $application,
        Employee $approver,
        ?string $remarks = null
    ): LeaveApplication {
        // Validate application is pending
        if ($application->status !== LeaveApplicationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending applications can be approved.',
            ]);
        }

        // Find the approval record for this approver at the current level
        $approval = $application->approvals()
            ->where('approval_level', $application->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', LeaveApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to approve this application at this level.',
            ]);
        }

        return DB::transaction(function () use ($application, $approval, $remarks) {
            // Record approval decision
            $approval->approve($remarks);

            // Check if this was the final approval
            if ($application->current_approval_level >= $application->total_approval_levels) {
                // Final approval - convert pending to used
                $application->leaveBalance?->convertPendingToUsed((float) $application->total_days);

                $application->status = LeaveApplicationStatus::Approved;
                $application->approved_at = now();
                $application->save();

                // Notify employee
                $application->employee->user?->notify(new LeaveApplicationApproved($application));
            } else {
                // Advance to next level
                $application->current_approval_level++;
                $application->save();

                // Notify next approver
                $nextApproval = $application->approvals()
                    ->where('approval_level', $application->current_approval_level)
                    ->first();

                if ($nextApproval && $nextApproval->approverEmployee) {
                    $nextApproval->approverEmployee->user?->notify(new LeaveApplicationSubmitted($application));
                }
            }

            return $application->fresh(['approvals', 'leaveType', 'employee']);
        });
    }

    /**
     * Reject a leave application.
     *
     * @throws ValidationException
     */
    public function reject(
        LeaveApplication $application,
        Employee $approver,
        string $reason
    ): LeaveApplication {
        // Validate application is pending
        if ($application->status !== LeaveApplicationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending applications can be rejected.',
            ]);
        }

        // Find the approval record for this approver
        $approval = $application->approvals()
            ->where('approval_level', $application->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', LeaveApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to reject this application.',
            ]);
        }

        return DB::transaction(function () use ($application, $approval, $reason) {
            // Record rejection
            $approval->reject($reason);

            // Release pending balance
            $application->leaveBalance?->releasePending((float) $application->total_days);

            // Update application status
            $application->status = LeaveApplicationStatus::Rejected;
            $application->rejected_at = now();
            $application->save();

            // Notify employee
            $application->employee->user?->notify(new LeaveApplicationRejected($application, $reason));

            return $application->fresh(['approvals', 'leaveType', 'employee']);
        });
    }

    /**
     * Cancel a leave application.
     *
     * @throws ValidationException
     */
    public function cancel(
        LeaveApplication $application,
        ?string $reason = null
    ): LeaveApplication {
        // Validate application can be cancelled
        if (! $application->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This application cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($application, $reason) {
            // Release pending balance if it was submitted
            if ($application->status === LeaveApplicationStatus::Pending) {
                $application->leaveBalance?->releasePending((float) $application->total_days);

                // Notify pending approvers
                $pendingApprovals = $application->approvals()
                    ->where('decision', LeaveApprovalDecision::Pending)
                    ->with('approverEmployee.user')
                    ->get();

                foreach ($pendingApprovals as $approval) {
                    $approval->approverEmployee?->user?->notify(
                        new LeaveApplicationCancelled($application)
                    );
                }
            }

            // Update application status
            $application->status = LeaveApplicationStatus::Cancelled;
            $application->cancelled_at = now();
            $application->cancellation_reason = $reason;
            $application->save();

            return $application->fresh(['approvals', 'leaveType', 'employee']);
        });
    }

    /**
     * Get or create a leave balance for the application's year.
     */
    protected function getOrCreateBalance(LeaveApplication $application): LeaveBalance
    {
        $year = $application->start_date->year;

        $balance = LeaveBalance::query()
            ->where('employee_id', $application->employee_id)
            ->where('leave_type_id', $application->leave_type_id)
            ->where('year', $year)
            ->first();

        if (! $balance) {
            // Create a new balance for this year
            $leaveType = $application->leaveType;
            $employee = $application->employee;

            $balance = LeaveBalance::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
                'earned' => $leaveType->calculateEntitlement($employee),
                'brought_forward' => 0,
                'used' => 0,
                'pending' => 0,
                'adjustments' => 0,
                'expired' => 0,
            ]);
        }

        return $balance;
    }

    /**
     * Check for overlapping leave applications.
     */
    public function hasOverlappingLeave(
        int $employeeId,
        string $startDate,
        string $endDate,
        ?int $excludeApplicationId = null
    ): bool {
        return LeaveApplication::overlapping($employeeId, $startDate, $endDate, $excludeApplicationId)
            ->exists();
    }

    /**
     * Calculate available balance for an employee and leave type.
     */
    public function getAvailableBalance(int $employeeId, int $leaveTypeId, int $year): float
    {
        $balance = LeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        return $balance ? $balance->available : 0;
    }
}
