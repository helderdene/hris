<?php

namespace App\Observers;

use App\Jobs\UpdateBillingQuantityJob;
use App\Models\Employee;

/**
 * Dispatches billing quantity sync when employee records change.
 */
class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        $this->dispatchBillingSync();
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        if ($employee->isDirty('employment_status')) {
            $this->dispatchBillingSync();
        }
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        $this->dispatchBillingSync();
    }

    /**
     * Dispatch the billing sync job for the current tenant.
     */
    private function dispatchBillingSync(): void
    {
        $tenant = tenant();
        if ($tenant) {
            UpdateBillingQuantityJob::dispatch($tenant);
        }
    }
}
