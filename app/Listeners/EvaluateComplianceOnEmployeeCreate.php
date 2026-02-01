<?php

namespace App\Listeners;

use App\Events\EmployeeCreated;
use App\Jobs\EvaluateComplianceRulesJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that evaluates compliance rules when a new employee is created.
 *
 * Implements ShouldQueue to process asynchronously and avoid
 * blocking the employee creation response.
 */
class EvaluateComplianceOnEmployeeCreate implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(EmployeeCreated $event): void
    {
        $employee = $event->employee;

        // Dispatch a job to evaluate compliance rules for this new hire
        EvaluateComplianceRulesJob::dispatch($employee, isNewHire: true);
    }
}
