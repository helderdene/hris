<?php

namespace App\Services\Visitor;

use App\Jobs\SyncVisitorToDeviceJob;
use App\Models\BiometricDevice;
use App\Models\Visitor;
use App\Models\VisitorDeviceSync;
use App\Models\WorkLocation;
use App\Services\Biometric\DeviceCommandService;
use Illuminate\Support\Collection;

class VisitorDeviceSyncService
{
    public function __construct(
        protected DeviceCommandService $deviceCommandService
    ) {}

    /**
     * Sync a visitor to a specific biometric device.
     */
    public function syncVisitorToDevice(Visitor $visitor, BiometricDevice $device): VisitorDeviceSync
    {
        $sync = VisitorDeviceSync::updateOrCreate(
            [
                'visitor_id' => $visitor->id,
                'biometric_device_id' => $device->id,
            ],
            [
                'status' => 'pending',
            ]
        );

        SyncVisitorToDeviceJob::dispatch($visitor, $device);

        return $sync;
    }

    /**
     * Sync a visitor to all biometric devices at a work location.
     *
     * @return Collection<int, VisitorDeviceSync>
     */
    public function syncVisitorToLocationDevices(Visitor $visitor, WorkLocation $location): Collection
    {
        $devices = $location->biometricDevices()
            ->where('status', 'online')
            ->get();

        return $devices->map(fn (BiometricDevice $device) => $this->syncVisitorToDevice($visitor, $device));
    }

    /**
     * Remove a visitor from a biometric device via MQTT.
     */
    public function unsyncVisitorFromDevice(Visitor $visitor, BiometricDevice $device): void
    {
        $this->deviceCommandService->deleteVisitorPerson($visitor, $device);

        VisitorDeviceSync::where('visitor_id', $visitor->id)
            ->where('biometric_device_id', $device->id)
            ->delete();
    }
}
