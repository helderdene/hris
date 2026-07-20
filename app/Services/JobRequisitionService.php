<?php

namespace App\Services;

use App\Enums\JobRequisitionStatus;
use App\Enums\LeaveApprovalDecision;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\JobRequisitionApproval;
use App\Models\User;
use App\Notifications\JobRequisitionApproved;
use App\Notifications\JobRequisitionCancelled;
use App\Notifications\JobRequisitionRejected;
use App\Notifications\JobRequisitionSubmitted;
use Illuminate\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing job requisition workflow.
 *
 * Handles submission, approval, rejection, and cancellation.
 */
class JobRequisitionService
{
    public function __construct(
        protected ApprovalChainResolver $chainResolver,
        protected JobPostingService $jobPostingService
    ) {}

    /**
     * Submit a job requisition for approval.
     *
     * - Builds approval chain
     * - Sends notifications to first approver
     *
     * @throws ValidationException
     */
    public function submit(JobRequisition $requisition): JobRequisition
    {
        if ($requisition->status !== JobRequisitionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft requisitions can be submitted.',
            ]);
        }

        $submitted = $requisition->getConnection()->transaction(function () use ($requisition) {
            // Build approval chain
            $employee = $requisition->requestedByEmployee;
            $approvers = $this->chainResolver->resolveChain($employee);

            if ($approvers->isEmpty()) {
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
                JobRequisitionApproval::create([
                    'job_requisition_id' => $requisition->id,
                    'approval_level' => $approverData['level'],
                    'approver_type' => $approverData['type'],
                    'approver_employee_id' => $approverData['employee']->id,
                    'approver_name' => $approverData['employee']->full_name,
                    'approver_position' => $approverData['employee']->position?->title,
                    'decision' => LeaveApprovalDecision::Pending,
                ]);
            }

            // Update requisition status
            $requisition->status = JobRequisitionStatus::Pending;
            $requisition->current_approval_level = 1;
            $requisition->total_approval_levels = $approvers->count();
            $requisition->submitted_at = now();

            $requisition->metadata = array_merge($requisition->metadata ?? [], [
                'submitted_by' => auth()->id(),
                'submitted_ip' => request()->ip(),
            ]);

            $requisition->save();

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });

        $firstApproval = $submitted->approvals->firstWhere('approval_level', 1);
        $this->notifyQuietly($firstApproval?->approverEmployee?->user, new JobRequisitionSubmitted($submitted));

        return $submitted;
    }

    /**
     * Approve a job requisition at the current level.
     *
     * @throws ValidationException
     */
    public function approve(
        JobRequisition $requisition,
        Employee $approver,
        ?string $remarks = null
    ): JobRequisition {
        if ($requisition->status !== JobRequisitionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requisitions can be approved.',
            ]);
        }

        $approval = $requisition->approvals()
            ->where('approval_level', $requisition->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', LeaveApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to approve this requisition at this level.',
            ]);
        }

        $approved = $requisition->getConnection()->transaction(function () use ($requisition, $approval, $remarks) {
            $approval->approve($remarks);

            if ($requisition->current_approval_level >= $requisition->total_approval_levels) {
                // Final approval
                $requisition->status = JobRequisitionStatus::Approved;
                $requisition->approved_at = now();
                $requisition->save();

                // Auto-create job posting from approved requisition
                $this->jobPostingService->createFromRequisition(
                    $requisition,
                    $requisition->requested_by_employee_id
                );
            } else {
                // Advance to next level
                $requisition->current_approval_level++;
                $requisition->save();
            }

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });

        if ($approved->status === JobRequisitionStatus::Approved) {
            $this->notifyQuietly($approved->requestedByEmployee?->user, new JobRequisitionApproved($approved));
        } else {
            $nextApproval = $approved->approvals->firstWhere('approval_level', $approved->current_approval_level);
            $this->notifyQuietly($nextApproval?->approverEmployee?->user, new JobRequisitionSubmitted($approved));
        }

        return $approved;
    }

    /**
     * Reject a job requisition.
     *
     * @throws ValidationException
     */
    public function reject(
        JobRequisition $requisition,
        Employee $approver,
        string $reason
    ): JobRequisition {
        if ($requisition->status !== JobRequisitionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requisitions can be rejected.',
            ]);
        }

        $approval = $requisition->approvals()
            ->where('approval_level', $requisition->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', LeaveApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to reject this requisition.',
            ]);
        }

        $rejected = $requisition->getConnection()->transaction(function () use ($requisition, $approval, $reason) {
            $approval->reject($reason);

            $requisition->status = JobRequisitionStatus::Rejected;
            $requisition->rejected_at = now();
            $requisition->save();

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });

        $this->notifyQuietly($rejected->requestedByEmployee?->user, new JobRequisitionRejected($rejected, $reason));

        return $rejected;
    }

    /**
     * Cancel a job requisition.
     *
     * @throws ValidationException
     */
    public function cancel(
        JobRequisition $requisition,
        ?string $reason = null
    ): JobRequisition {
        if (! $requisition->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This requisition cannot be cancelled.',
            ]);
        }

        $pendingApproverUsers = $requisition->approvals()
            ->where('decision', LeaveApprovalDecision::Pending)
            ->get()
            ->map(fn (JobRequisitionApproval $approval) => $approval->approverEmployee?->user)
            ->filter();

        $cancelled = $requisition->getConnection()->transaction(function () use ($requisition, $reason) {
            $requisition->status = JobRequisitionStatus::Cancelled;
            $requisition->cancelled_at = now();
            $requisition->cancellation_reason = $reason;
            $requisition->save();

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });

        foreach ($pendingApproverUsers as $user) {
            $this->notifyQuietly($user, new JobRequisitionCancelled($cancelled));
        }

        return $cancelled;
    }

    /**
     * Send a notification after the surrounding transaction has committed.
     *
     * Delivery failures are reported instead of thrown so a mail or broadcast
     * outage cannot fail the request after state has already been persisted.
     */
    protected function notifyQuietly(?User $user, Notification $notification): void
    {
        if ($user) {
            rescue(fn () => $user->notify($notification));
        }
    }
}
