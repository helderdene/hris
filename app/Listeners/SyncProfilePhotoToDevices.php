<?php

namespace App\Listeners;

use App\Events\ProfilePhotoUploaded;
use App\Jobs\SyncEmployeeToDeviceJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener that queues sync jobs when a profile photo is uploaded.
 *
 * Implements ShouldQueue to process asynchronously and avoid
 * blocking the document upload response.
 */
class SyncProfilePhotoToDevices implements ShouldQueue
{
    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 60;

    /**
     * Handle the event.
     */
    public function handle(ProfilePhotoUploaded $event): void
    {
        $employee = $event->employee;
        $tenant = tenant();

        if ($tenant === null) {
            Log::warning('SyncProfilePhotoToDevices: No tenant context available', [
                'employee_id' => $employee->id,
            ]);

            return;
        }

        $devices = $employee->getDevicesToSyncTo();

        if ($devices->isEmpty()) {
            Log::info('SyncProfilePhotoToDevices: No devices to sync to', [
                'employee_id' => $employee->id,
                'work_location_id' => $employee->work_location_id,
            ]);

            return;
        }

        Log::info('SyncProfilePhotoToDevices: Queuing sync jobs', [
            'employee_id' => $employee->id,
            'device_count' => $devices->count(),
        ]);

        foreach ($devices as $device) {
            SyncEmployeeToDeviceJob::dispatch(
                $employee->id,
                $device->id,
                $tenant->id
            );
        }
    }
}
