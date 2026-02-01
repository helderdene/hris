<?php

namespace App\Listeners;

use App\Events\EmployeePositionChanged;
use App\Jobs\EvaluateComplianceRulesJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that evaluates compliance rules when an employee changes positions.
 *
 * Implements ShouldQueue to process asynchronously and avoid
 * blocking the employee update response.
 */
class EvaluateComplianceOnPositionChange implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(EmployeePositionChanged $event): void
    {
        $employee = $event->employee;

        // Dispatch a job to evaluate compliance rules for the new position
        EvaluateComplianceRulesJob::dispatch($employee, isNewHire: false);
    }
}
