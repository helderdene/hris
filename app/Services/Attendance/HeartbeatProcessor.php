<?php

namespace App\Services\Attendance;

use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Support\Facades\Log;

/**
 * Processes MQTT heartbeat messages from biometric devices.
 *
 * Updates device connectivity status without requiring attendance records.
 */
class HeartbeatProcessor
{
    public function __construct(
        private TenantDatabaseManager $tenantManager
    ) {}

    /**
     * Process a heartbeat message payload.
     */
    public function process(string $payload): bool
    {
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            Log::warning('Heartbeat: invalid JSON payload', [
                'raw_payload' => mb_substr($payload, 0, 500),
            ]);

            return false;
        }

        $deviceIdentifier = $this->extractDeviceIdentifier($data);

        if ($deviceIdentifier === null) {
            Log::warning('Heartbeat: unable to extract device identifier', [
                'raw_payload' => mb_substr($payload, 0, 500),
            ]);

            return false;
        }

        $deviceWithTenant = $this->findDeviceWithTenant($deviceIdentifier);

        if ($deviceWithTenant === null) {
            Log::debug('Heartbeat: unknown device', [
                'device_identifier' => $deviceIdentifier,
            ]);

            return false;
        }

        [$device, $tenant] = $deviceWithTenant;

        $this->tenantManager->switchConnection($tenant);
        app()->instance('tenant', $tenant);

        $updates = [
            'last_seen_at' => now(),
            'status' => 'online',
        ];

        if ($device->connection_started_at === null) {
            $updates['connection_started_at'] = now();
        }

        $device->update($updates);

        return true;
    }

    /**
     * Extract device identifier from heartbeat payload.
     *
     * Supports formats:
     * - {"operator": "HeartBeat", "info": {"facesluiceId": "123"}}
     * - {"facesluiceId": "123"}
     * - {"deviceId": "123"}
     */
    private function extractDeviceIdentifier(array $data): ?string
    {
        // Nested in info object (standard FR device format)
        $identifier = $data['info']['facesluiceId'] ?? null;

        // Top-level facesluiceId
        if ($identifier === null) {
            $identifier = $data['facesluiceId'] ?? null;
        }

        // Fallback to deviceId
        if ($identifier === null) {
            $identifier = $data['info']['deviceId'] ?? $data['deviceId'] ?? null;
        }

        if ($identifier === null) {
            return null;
        }

        $identifier = (string) $identifier;

        return $identifier !== '' ? $identifier : null;
    }

    /**
     * Find a biometric device across all tenants.
     *
     * @return array{0: BiometricDevice, 1: Tenant}|null
     */
    private function findDeviceWithTenant(string $deviceIdentifier): ?array
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            try {
                $this->tenantManager->switchConnection($tenant);

                $device = BiometricDevice::where('device_identifier', $deviceIdentifier)
                    ->where('is_active', true)
                    ->first();

                if ($device !== null) {
                    return [$device, $tenant];
                }
            } catch (\Throwable $e) {
                Log::debug("Heartbeat: skipping tenant {$tenant->slug}", [
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return null;
    }
}
