<?php

namespace App\Jobs;

use App\Models\BiometricDevice;
use App\Models\Visitor;
use App\Models\VisitorDeviceSync;
use App\Services\Biometric\DeviceCommandService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncVisitorToDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Visitor $visitor,
        public BiometricDevice $device
    ) {}

    public function handle(DeviceCommandService $commandService): void
    {
        $sync = VisitorDeviceSync::where('visitor_id', $this->visitor->id)
            ->where('biometric_device_id', $this->device->id)
            ->first();

        if (! $sync) {
            return;
        }

        try {
            $sync->markSyncing();

            $commandService->editVisitorPerson($this->visitor, $this->device);

            $sync->markSynced();
        } catch (\Throwable $e) {
            Log::error('Failed to sync visitor to device', [
                'visitor_id' => $this->visitor->id,
                'device_id' => $this->device->id,
                'error' => $e->getMessage(),
            ]);

            $sync->markFailed($e->getMessage());
        }
    }
}
