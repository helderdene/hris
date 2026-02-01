<?php

namespace App\Services;

use App\Enums\JobRequisitionStatus;
use App\Enums\LeaveApprovalDecision;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\JobRequisitionApproval;
use App\Notifications\JobRequisitionApproved;
use App\Notifications\JobRequisitionCancelled;
use App\Notifications\JobRequisitionRejected;
use App\Notifications\JobRequisitionSubmitted;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($requisition) {
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

            // Notify first approver
            $firstApproval = $requisition->approvals()->where('approval_level', 1)->first();
            $firstApproval?->approverEmployee?->user?->notify(new JobRequisitionSubmitted($requisition));

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });
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

        return DB::transaction(function () use ($requisition, $approval, $remarks) {
            $approval->approve($remarks);

            if ($requisition->current_approval_level >= $requisition->total_approval_levels) {
                // Final approval
                $requisition->status = JobRequisitionStatus::Approved;
                $requisition->approved_at = now();
                $requisition->save();

                // Notify requester
                $requisition->requestedByEmployee?->user?->notify(new JobRequisitionApproved($requisition));

                // Auto-create job posting from approved requisition
                $this->jobPostingService->createFromRequisition(
                    $requisition,
                    $requisition->requested_by_employee_id
                );
            } else {
                // Advance to next level
                $requisition->current_approval_level++;
                $requisition->save();

                // Notify next approver
                $nextApproval = $requisition->approvals()
                    ->where('approval_level', $requisition->current_approval_level)
                    ->first();
                $nextApproval?->approverEmployee?->user?->notify(new JobRequisitionSubmitted($requisition));
            }

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });
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

        return DB::transaction(function () use ($requisition, $approval, $reason) {
            $approval->reject($reason);

            $requisition->status = JobRequisitionStatus::Rejected;
            $requisition->rejected_at = now();
            $requisition->save();

            // Notify requester
            $requisition->requestedByEmployee?->user?->notify(new JobRequisitionRejected($requisition, $reason));

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });
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

        return DB::transaction(function () use ($requisition, $reason) {
            // Notify pending approvers before status change
            $requisition->approvals()
                ->where('decision', LeaveApprovalDecision::Pending)
                ->each(function (JobRequisitionApproval $approval) use ($requisition) {
                    $approval->approverEmployee?->user?->notify(new JobRequisitionCancelled($requisition));
                });

            $requisition->status = JobRequisitionStatus::Cancelled;
            $requisition->cancelled_at = now();
            $requisition->cancellation_reason = $reason;
            $requisition->save();

            return $requisition->fresh(['approvals', 'position', 'department', 'requestedByEmployee']);
        });
    }
}
