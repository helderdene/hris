<?php

namespace App\Jobs;

use App\Enums\VisitStatus;
use App\Models\VisitorVisit;
use App\Services\Visitor\VisitorDeviceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupVisitorDeviceSyncsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(VisitorDeviceSyncService $syncService): void
    {
        // Find visits that were checked out more than 24 hours ago
        $visits = VisitorVisit::query()
            ->where('status', VisitStatus::CheckedOut)
            ->where('checked_out_at', '<', now()->subDay())
            ->whereHas('visitor', function ($q) {
                $q->whereHas('deviceSyncs');
            })
            ->with(['visitor.deviceSyncs.biometricDevice'])
            ->get();

        foreach ($visits as $visit) {
            foreach ($visit->visitor->deviceSyncs as $sync) {
                try {
                    $syncService->unsyncVisitorFromDevice($visit->visitor, $sync->biometricDevice);
                } catch (\Throwable $e) {
                    Log::warning('Failed to cleanup visitor device sync', [
                        'visitor_id' => $visit->visitor_id,
                        'device_id' => $sync->biometric_device_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
