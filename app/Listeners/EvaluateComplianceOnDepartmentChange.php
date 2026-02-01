<?php

namespace App\Listeners;

use App\Events\EmployeeDepartmentChanged;
use App\Jobs\EvaluateComplianceRulesJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that evaluates compliance rules when an employee changes departments.
 *
 * Implements ShouldQueue to process asynchronously and avoid
 * blocking the employee update response.
 */
class EvaluateComplianceOnDepartmentChange implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(EmployeeDepartmentChanged $event): void
    {
        $employee = $event->employee;

        // Dispatch a job to evaluate compliance rules for the new department
        EvaluateComplianceRulesJob::dispatch($employee, isNewHire: false);
    }
}
