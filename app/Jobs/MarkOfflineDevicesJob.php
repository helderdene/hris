<?php

namespace App\Jobs;

use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Marks biometric devices as offline when no heartbeat has been received recently.
 *
 * Devices with a last_seen_at older than the staleness threshold are considered disconnected.
 */
class MarkOfflineDevicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Minutes of silence before a device is considered offline.
     */
    protected int $stalenessMinutes = 3;

    /**
     * Execute the job.
     */
    public function handle(TenantDatabaseManager $databaseManager): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->processTenanDevices($tenant, $databaseManager);
        }
    }

    /**
     * Mark stale online devices as offline for a single tenant.
     */
    protected function processTenanDevices(
        Tenant $tenant,
        TenantDatabaseManager $databaseManager
    ): void {
        try {
            $databaseManager->switchConnection($tenant);
            app()->instance('tenant', $tenant);

            $threshold = now()->subMinutes($this->stalenessMinutes);

            $staleCount = BiometricDevice::query()
                ->where('status', 'online')
                ->where('last_seen_at', '<', $threshold)
                ->update([
                    'status' => 'offline',
                    'connection_started_at' => null,
                ]);

            if ($staleCount > 0) {
                Log::info('Marked devices offline', [
                    'tenant' => $tenant->slug,
                    'count' => $staleCount,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error marking offline devices', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
