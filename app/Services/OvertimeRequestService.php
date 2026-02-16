<?php

namespace App\Services;

use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeRequestStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRequestApproval;
use App\Notifications\OvertimeRequestApproved;
use App\Notifications\OvertimeRequestRejected;
use App\Notifications\OvertimeRequestSubmitted;
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
            $employee = $request->employee;
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
            $request->total_approval_levels = $approvers->count();
            $request->submitted_at = now();
            $request->metadata = array_merge($request->metadata ?? [], [
                'submitted_by' => auth()->id(),
                'submitted_ip' => request()->ip(),
            ]);
            $request->save();

            $firstApproval = $request->approvals()->where('approval_level', 1)->first();
            if ($firstApproval && $firstApproval->approverEmployee) {
                $firstApproval->approverEmployee->user?->notify(new OvertimeRequestSubmitted($request));
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

        $approval = $request->approvals()
            ->where('approval_level', $request->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', OvertimeApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to approve this request at this level.',
            ]);
        }

        return DB::transaction(function () use ($request, $approval, $remarks) {
            $approval->approve($remarks);

            if ($request->current_approval_level >= $request->total_approval_levels) {
                $request->status = OvertimeRequestStatus::Approved;
                $request->approved_at = now();
                $request->save();

                $this->linkToDtr($request);

                $request->employee->user?->notify(new OvertimeRequestApproved($request));
            } else {
                $request->current_approval_level++;
                $request->save();

                $nextApproval = $request->approvals()
                    ->where('approval_level', $request->current_approval_level)
                    ->first();

                if ($nextApproval && $nextApproval->approverEmployee) {
                    $nextApproval->approverEmployee->user?->notify(new OvertimeRequestSubmitted($request));
                }
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

        $approval = $request->approvals()
            ->where('approval_level', $request->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', OvertimeApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to reject this request.',
            ]);
        }

        return DB::transaction(function () use ($request, $approval, $reason) {
            $approval->reject($reason);

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
            $request->status = OvertimeRequestStatus::Cancelled;
            $request->cancelled_at = now();
            $request->cancellation_reason = $reason;
            $request->save();

            return $request->fresh(['approvals', 'employee']);
        });
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
