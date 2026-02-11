<?php

namespace App\Listeners;

use App\Events\EmployeeCreated;
use App\Services\Biometric\EmployeeSyncService;

/**
 * Listener that creates pending biometric sync records when a new employee is created.
 *
 * If the employee has a work location with biometric devices,
 * pending sync records are initialized so the UI can show sync status.
 */
class InitializeBiometricSyncRecords
{
    public function __construct(
        protected EmployeeSyncService $syncService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EmployeeCreated $event): void
    {
        $this->syncService->initializeSyncRecords($event->employee);
    }
}
