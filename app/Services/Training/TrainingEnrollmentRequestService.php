<?php

namespace App\Services\Training;

use App\Enums\EnrollmentStatus;
use App\Enums\SessionStatus;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use App\Notifications\TrainingEnrollmentApproved;
use App\Notifications\TrainingEnrollmentRejected;
use App\Notifications\TrainingEnrollmentRequestSubmitted;
use App\Services\ApprovalChainResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing training enrollment request workflow.
 *
 * Handles submission, approval, rejection, and cancellation with
 * supervisor approval and notifications.
 */
class TrainingEnrollmentRequestService
{
    public function __construct(
        protected ApprovalChainResolver $chainResolver
    ) {}

    /**
     * Submit an enrollment request for approval.
     *
     * @throws ValidationException
     */
    public function submit(
        TrainingSession $session,
        Employee $employee,
        ?string $reason = null
    ): TrainingEnrollment {
        $this->validateSessionCanAcceptEnrollments($session);
        $this->validateEmployeeCanEnroll($session, $employee);

        return DB::transaction(function () use ($session, $employee, $reason) {
            $approver = $this->resolveApprover($employee);

            $enrollment = TrainingEnrollment::create([
                'training_session_id' => $session->id,
                'employee_id' => $employee->id,
                'status' => EnrollmentStatus::Pending,
                'reference_number' => TrainingEnrollment::generateReferenceNumber(),
                'submitted_at' => now(),
                'request_reason' => $reason,
                'approver_employee_id' => $approver->id,
                'approver_name' => $approver->full_name,
                'approver_position' => $approver->position?->name,
            ]);

            $approver->user?->notify(new TrainingEnrollmentRequestSubmitted($enrollment));

            return $enrollment->fresh(['session', 'employee', 'approver']);
        });
    }

    /**
     * Approve an enrollment request.
     *
     * @throws ValidationException
     */
    public function approve(
        TrainingEnrollment $enrollment,
        Employee $approver,
        ?string $remarks = null
    ): TrainingEnrollment {
        if (! $enrollment->canBeApprovedBy($approver)) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to approve this request.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $remarks) {
            $session = $enrollment->session;

            if ($session->is_full) {
                return $this->addToWaitlist($enrollment, $remarks);
            }

            $enrollment->status = EnrollmentStatus::Confirmed;
            $enrollment->enrolled_at = now();
            $enrollment->approved_at = now();
            $enrollment->approver_remarks = $remarks;
            $enrollment->save();

            $enrollment->employee->user?->notify(new TrainingEnrollmentApproved($enrollment));

            return $enrollment->fresh(['session', 'employee', 'approver']);
        });
    }

    /**
     * Reject an enrollment request.
     *
     * @throws ValidationException
     */
    public function reject(
        TrainingEnrollment $enrollment,
        Employee $approver,
        string $reason
    ): TrainingEnrollment {
        if (! $enrollment->canBeRejectedBy($approver)) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to reject this request.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->status = EnrollmentStatus::Rejected;
            $enrollment->rejected_at = now();
            $enrollment->rejection_reason = $reason;
            $enrollment->save();

            $enrollment->employee->user?->notify(new TrainingEnrollmentRejected($enrollment, $reason));

            return $enrollment->fresh(['session', 'employee', 'approver']);
        });
    }

    /**
     * Cancel a pending enrollment request (by the employee).
     *
     * @throws ValidationException
     */
    public function cancel(
        TrainingEnrollment $enrollment,
        Employee $employee,
        ?string $reason = null
    ): TrainingEnrollment {
        if ($enrollment->employee_id !== $employee->id) {
            throw ValidationException::withMessages([
                'employee' => 'You can only cancel your own enrollment requests.',
            ]);
        }

        if (! $enrollment->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This enrollment request cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $employee, $reason) {
            $enrollment->status = EnrollmentStatus::Cancelled;
            $enrollment->cancelled_at = now();
            $enrollment->cancelled_by = $employee->id;
            $enrollment->cancellation_reason = $reason;
            $enrollment->save();

            return $enrollment->fresh(['session', 'employee', 'approver']);
        });
    }

    /**
     * Check if an employee has a pending request for a session.
     */
    public function hasPendingRequest(TrainingSession $session, Employee $employee): bool
    {
        return TrainingEnrollment::query()
            ->where('training_session_id', $session->id)
            ->where('employee_id', $employee->id)
            ->where('status', EnrollmentStatus::Pending)
            ->exists();
    }

    /**
     * Get pending enrollment requests for an approver.
     *
     * @return \Illuminate\Database\Eloquent\Collection<TrainingEnrollment>
     */
    public function getPendingForApprover(Employee $approver): \Illuminate\Database\Eloquent\Collection
    {
        return TrainingEnrollment::query()
            ->pendingForApprover($approver)
            ->with(['session.course', 'employee.position', 'employee.department'])
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Validate that the session can accept enrollments.
     *
     * @throws ValidationException
     */
    protected function validateSessionCanAcceptEnrollments(TrainingSession $session): void
    {
        if ($session->status !== SessionStatus::Scheduled) {
            throw ValidationException::withMessages([
                'session' => 'This session is not open for enrollment.',
            ]);
        }

        if ($session->start_date->isPast()) {
            throw ValidationException::withMessages([
                'session' => 'This session has already started.',
            ]);
        }
    }

    /**
     * Validate that the employee can enroll in the session.
     *
     * @throws ValidationException
     */
    protected function validateEmployeeCanEnroll(TrainingSession $session, Employee $employee): void
    {
        $existingEnrollment = TrainingEnrollment::query()
            ->where('training_session_id', $session->id)
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Confirmed->value,
            ])
            ->first();

        if ($existingEnrollment) {
            $status = $existingEnrollment->status;
            if ($status === EnrollmentStatus::Pending) {
                throw ValidationException::withMessages([
                    'employee' => 'You already have a pending enrollment request for this session.',
                ]);
            }
            throw ValidationException::withMessages([
                'employee' => 'You are already enrolled in this session.',
            ]);
        }

        if ($session->hasEmployeeOnWaitlist($employee)) {
            throw ValidationException::withMessages([
                'employee' => 'You are already on the waitlist for this session.',
            ]);
        }
    }

    /**
     * Resolve the approver for the employee.
     *
     * @throws ValidationException
     */
    protected function resolveApprover(Employee $employee): Employee
    {
        $approver = $this->chainResolver->getFirstApprover($employee);

        if (! $approver) {
            $approver = $this->chainResolver->getFallbackApprover($employee);
        }

        if (! $approver) {
            throw ValidationException::withMessages([
                'approver' => 'No approver found. Please contact HR.',
            ]);
        }

        return $approver;
    }

    /**
     * Add an approved enrollment to the waitlist when session is full.
     */
    protected function addToWaitlist(TrainingEnrollment $enrollment, ?string $remarks): TrainingEnrollment
    {
        $enrollment->status = EnrollmentStatus::Cancelled;
        $enrollment->approved_at = now();
        $enrollment->approver_remarks = $remarks;
        $enrollment->cancellation_reason = 'Session full - added to waitlist';
        $enrollment->save();

        TrainingWaitlist::create([
            'training_session_id' => $enrollment->training_session_id,
            'employee_id' => $enrollment->employee_id,
            'position' => TrainingWaitlist::getNextPosition($enrollment->training_session_id),
            'joined_at' => now(),
        ]);

        $enrollment->employee->user?->notify(new TrainingEnrollmentApproved($enrollment, true));

        return $enrollment->fresh(['session', 'employee', 'approver']);
    }
}
