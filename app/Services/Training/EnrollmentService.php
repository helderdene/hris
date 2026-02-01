<?php

namespace App\Services\Training;

use App\Enums\EnrollmentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use App\Notifications\TrainingEnrollmentConfirmed;
use App\Notifications\TrainingSessionCancelled;
use App\Notifications\TrainingWaitlistJoined;
use App\Notifications\TrainingWaitlistPromoted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing training session enrollments and waitlists.
 *
 * Handles enrollment, cancellation, and waitlist promotion with notifications.
 */
class EnrollmentService
{
    /**
     * Enroll an employee in a training session.
     *
     * If the session is full, adds to waitlist instead.
     *
     * @throws ValidationException
     */
    public function enroll(
        TrainingSession $session,
        Employee $employee,
        ?Employee $enrolledBy = null,
        ?string $notes = null
    ): TrainingEnrollment|TrainingWaitlist {
        // Validate session is available for enrollment
        if (! $session->status->isEnrollable()) {
            throw ValidationException::withMessages([
                'session' => 'This session is not available for enrollment.',
            ]);
        }

        // Check if already enrolled
        if ($session->hasEmployee($employee)) {
            throw ValidationException::withMessages([
                'employee' => 'This employee is already enrolled in this session.',
            ]);
        }

        // Check if already on waitlist
        if ($session->hasEmployeeOnWaitlist($employee)) {
            throw ValidationException::withMessages([
                'employee' => 'This employee is already on the waitlist for this session.',
            ]);
        }

        return DB::transaction(function () use ($session, $employee, $enrolledBy, $notes) {
            // Refresh session to get accurate count
            $session->refresh();

            if ($session->is_full) {
                return $this->addToWaitlist($session, $employee);
            }

            return $this->createEnrollment($session, $employee, $enrolledBy, $notes);
        });
    }

    /**
     * Create an enrollment record.
     */
    protected function createEnrollment(
        TrainingSession $session,
        Employee $employee,
        ?Employee $enrolledBy = null,
        ?string $notes = null
    ): TrainingEnrollment {
        $enrollment = TrainingEnrollment::create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'status' => EnrollmentStatus::Confirmed,
            'enrolled_at' => now(),
            'enrolled_by' => $enrolledBy?->id,
            'notes' => $notes,
        ]);

        // Send notification
        $employee->user?->notify(new TrainingEnrollmentConfirmed($enrollment));

        return $enrollment;
    }

    /**
     * Add an employee to the session waitlist.
     */
    protected function addToWaitlist(
        TrainingSession $session,
        Employee $employee
    ): TrainingWaitlist {
        $waitlist = TrainingWaitlist::create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'status' => WaitlistStatus::Waiting,
            'position' => TrainingWaitlist::getNextPosition($session->id),
            'joined_at' => now(),
        ]);

        // Send notification
        $employee->user?->notify(new TrainingWaitlistJoined($waitlist));

        return $waitlist;
    }

    /**
     * Cancel an enrollment.
     *
     * Triggers waitlist promotion if there are waiting entries.
     *
     * @throws ValidationException
     */
    public function cancelEnrollment(
        TrainingEnrollment $enrollment,
        ?Employee $cancelledBy = null,
        ?string $reason = null
    ): TrainingEnrollment {
        if (! $enrollment->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'enrollment' => 'This enrollment cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $cancelledBy, $reason) {
            $enrollment->cancel($cancelledBy, $reason);

            // Try to promote from waitlist
            $this->promoteFromWaitlist($enrollment->session);

            return $enrollment->fresh();
        });
    }

    /**
     * Remove an employee from the waitlist.
     *
     * @throws ValidationException
     */
    public function cancelWaitlist(TrainingWaitlist $waitlist): TrainingWaitlist
    {
        if (! $waitlist->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'waitlist' => 'This waitlist entry cannot be cancelled.',
            ]);
        }

        $waitlist->cancel();

        return $waitlist->fresh();
    }

    /**
     * Promote the next person from the waitlist to enrollment.
     */
    public function promoteFromWaitlist(TrainingSession $session): ?TrainingEnrollment
    {
        // Don't promote if session is full or not enrollable
        if ($session->is_full || ! $session->status->isEnrollable()) {
            return null;
        }

        return DB::transaction(function () use ($session) {
            // Get the next waiting entry (FIFO)
            $waitlistEntry = $session->waitlist()
                ->waiting()
                ->ordered()
                ->lockForUpdate()
                ->first();

            if (! $waitlistEntry) {
                return null;
            }

            // Mark as promoted
            $waitlistEntry->promote();

            // Create enrollment
            $enrollment = TrainingEnrollment::create([
                'training_session_id' => $session->id,
                'employee_id' => $waitlistEntry->employee_id,
                'status' => EnrollmentStatus::Confirmed,
                'enrolled_at' => now(),
                'notes' => 'Promoted from waitlist',
            ]);

            // Send notification
            $waitlistEntry->employee->user?->notify(new TrainingWaitlistPromoted($enrollment));

            return $enrollment;
        });
    }

    /**
     * Mark an enrollment as attended.
     *
     * @throws ValidationException
     */
    public function markAsAttended(TrainingEnrollment $enrollment): TrainingEnrollment
    {
        if (! $enrollment->status->canMarkAttendance()) {
            throw ValidationException::withMessages([
                'enrollment' => 'Cannot mark attendance for this enrollment.',
            ]);
        }

        $enrollment->markAsAttended();

        return $enrollment->fresh();
    }

    /**
     * Mark an enrollment as no-show.
     *
     * @throws ValidationException
     */
    public function markAsNoShow(TrainingEnrollment $enrollment): TrainingEnrollment
    {
        if (! $enrollment->status->canMarkAttendance()) {
            throw ValidationException::withMessages([
                'enrollment' => 'Cannot mark attendance for this enrollment.',
            ]);
        }

        $enrollment->markAsNoShow();

        return $enrollment->fresh();
    }

    /**
     * Cancel a session and notify all enrolled employees.
     */
    public function cancelSession(TrainingSession $session, ?string $reason = null): TrainingSession
    {
        if (! $session->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'session' => 'This session cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($session, $reason) {
            // Get all active enrollments before cancelling
            $enrollments = $session->activeEnrollments()->with('employee.user')->get();
            $waitlistEntries = $session->activeWaitlist()->with('employee.user')->get();

            // Cancel the session
            $session->cancel();

            // Cancel all enrollments
            foreach ($enrollments as $enrollment) {
                $enrollment->update([
                    'status' => EnrollmentStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Session cancelled: '.($reason ?? 'No reason provided'),
                ]);

                // Notify employee
                $enrollment->employee->user?->notify(new TrainingSessionCancelled($session, $reason));
            }

            // Cancel all waitlist entries
            foreach ($waitlistEntries as $entry) {
                $entry->update([
                    'status' => WaitlistStatus::Cancelled,
                ]);
            }

            return $session->fresh();
        });
    }

    /**
     * Bulk enroll multiple employees in a session.
     *
     * @param  array<int>  $employeeIds
     * @return array<int, TrainingEnrollment|TrainingWaitlist>
     */
    public function bulkEnroll(
        TrainingSession $session,
        array $employeeIds,
        ?Employee $enrolledBy = null
    ): array {
        $results = [];

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);

            if ($employee) {
                try {
                    $results[$employeeId] = $this->enroll($session, $employee, $enrolledBy);
                } catch (ValidationException $e) {
                    // Skip if already enrolled or on waitlist
                    continue;
                }
            }
        }

        return $results;
    }
}
