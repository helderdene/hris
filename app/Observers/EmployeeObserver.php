<?php

namespace App\Observers;

use App\Jobs\UpdateBillingQuantityJob;
use App\Models\Employee;
use App\Models\User;

/**
 * Dispatches billing quantity sync when employee records change.
 */
class EmployeeObserver
{
    /**
     * Link an employee without a login to the tenant user who has the same email,
     * when the employee is created or their email changes.
     */
    public function saving(Employee $employee): void
    {
        if ($employee->user_id !== null || ! $employee->email) {
            return;
        }

        if ($employee->exists && ! $employee->isDirty('email')) {
            return;
        }

        $employee->user_id = $this->linkableUserId($employee->email);
    }

    /**
     * Find the member of the current tenant who owns this email and has no employee record yet.
     */
    private function linkableUserId(string $email): ?int
    {
        $tenant = tenant();
        $user = $tenant ? User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first() : null;
        $membership = $user?->tenants()->where('tenant_id', $tenant->id)->first();

        if (! $membership) {
            return null;
        }

        if (! $user->ownsEmail()) {
            return null;
        }

        return Employee::query()->where('user_id', $user->id)->exists() ? null : $user->id;
    }

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
