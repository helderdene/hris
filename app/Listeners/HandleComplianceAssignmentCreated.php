<?php

namespace App\Listeners;

use App\Events\ComplianceAssignmentCreated;
use App\Notifications\ComplianceTrainingAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that handles new compliance assignment creation.
 *
 * Implements ShouldQueue to process asynchronously.
 */
class HandleComplianceAssignmentCreated implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ComplianceAssignmentCreated $event): void
    {
        $assignment = $event->assignment;
        $employee = $assignment->employee;
        $user = $employee?->user;

        // Notify the employee about the new assignment
        if ($user) {
            $user->notify(new ComplianceTrainingAssigned($assignment));
        }
    }
}
