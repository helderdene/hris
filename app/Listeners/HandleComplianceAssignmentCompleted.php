<?php

namespace App\Listeners;

use App\Events\ComplianceAssignmentCompleted;
use App\Jobs\GenerateComplianceCertificateJob;
use App\Notifications\ComplianceCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that handles compliance assignment completion.
 *
 * Implements ShouldQueue to process asynchronously.
 */
class HandleComplianceAssignmentCompleted implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ComplianceAssignmentCompleted $event): void
    {
        $assignment = $event->assignment;
        $employee = $assignment->employee;
        $user = $employee?->user;

        // Notify the employee
        if ($user) {
            $user->notify(new ComplianceCompleted($assignment));
        }

        // Generate certificate if applicable
        GenerateComplianceCertificateJob::dispatch($assignment);
    }
}
