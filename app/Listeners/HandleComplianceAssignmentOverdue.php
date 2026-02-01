<?php

namespace App\Listeners;

use App\Events\ComplianceAssignmentOverdue;
use App\Jobs\EscalateOverdueComplianceJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that handles compliance assignment becoming overdue.
 *
 * Implements ShouldQueue to process asynchronously.
 */
class HandleComplianceAssignmentOverdue implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ComplianceAssignmentOverdue $event): void
    {
        $assignment = $event->assignment;
        $daysOverdue = $assignment->due_date->diffInDays(now());

        // Escalate to manager after 1 day overdue
        if ($daysOverdue >= 1) {
            EscalateOverdueComplianceJob::dispatch(
                $assignment,
                $daysOverdue,
                'manager'
            );
        }

        // Escalate to HR after 7 days overdue
        if ($daysOverdue >= 7) {
            EscalateOverdueComplianceJob::dispatch(
                $assignment,
                $daysOverdue,
                'hr'
            );
        }
    }
}
