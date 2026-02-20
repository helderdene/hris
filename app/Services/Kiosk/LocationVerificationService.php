<?php

namespace App\Services\Kiosk;

use App\Models\WorkLocation;
use Illuminate\Http\Request;

class LocationVerificationService
{
    /**
     * Verify the request against a work location's check mode.
     *
     * @param  array{latitude?: float, longitude?: float, accuracy?: float}|null  $gpsData
     * @return array{passed: bool, reason: ?string}
     */
    public function verify(Request $request, WorkLocation $location, ?array $gpsData = null): array
    {
        $mode = $location->location_check ?? 'none';

        if ($mode === 'none') {
            return ['passed' => true, 'reason' => null];
        }

        $ipResult = null;
        $gpsResult = null;

        if (in_array($mode, ['ip', 'both', 'any'])) {
            $ipResult = $this->verifyIp($request->ip(), $location->ip_whitelist ?? []);
        }

        if (in_array($mode, ['gps', 'both', 'any'])) {
            if ($gpsData && isset($gpsData['latitude'], $gpsData['longitude'])) {
                $gpsResult = $this->verifyGps(
                    (float) $gpsData['latitude'],
                    (float) $gpsData['longitude'],
                    (float) ($gpsData['accuracy'] ?? 0),
                    $location,
                );
            } else {
                $gpsResult = false;
            }
        }

        return match ($mode) {
            'ip' => $ipResult
                ? ['passed' => true, 'reason' => null]
                : ['passed' => false, 'reason' => 'Your IP address is not whitelisted for this location.'],
            'gps' => $gpsResult
                ? ['passed' => true, 'reason' => null]
                : ['passed' => false, 'reason' => 'You are outside the allowed geofence area.'],
            'both' => ($ipResult && $gpsResult)
                ? ['passed' => true, 'reason' => null]
                : ['passed' => false, 'reason' => 'Both IP and GPS verification are required.'],
            'any' => ($ipResult || $gpsResult)
                ? ['passed' => true, 'reason' => null]
                : ['passed' => false, 'reason' => 'Neither IP nor GPS verification passed.'],
            default => ['passed' => true, 'reason' => null],
        };
    }

    /**
     * Verify if the client IP matches the whitelist.
     *
     * Supports both exact IPs and CIDR notation.
     */
    public function verifyIp(string $clientIp, array $whitelist): bool
    {
        if (empty($whitelist)) {
            return true;
        }

        foreach ($whitelist as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($clientIp, $entry)) {
                    return true;
                }
            } elseif ($clientIp === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify if GPS coordinates are within the geofence.
     */
    public function verifyGps(float $lat, float $lng, float $accuracy, WorkLocation $location): bool
    {
        if ($location->latitude === null || $location->longitude === null || $location->geofence_radius === null) {
            return true;
        }

        // Reject if GPS accuracy is too poor (> 200m)
        if ($accuracy > 200) {
            return false;
        }

        $distance = $this->haversineDistance(
            $lat, $lng,
            (float) $location->latitude, (float) $location->longitude,
        );

        return $distance <= $location->geofence_radius;
    }

    /**
     * Calculate the Haversine distance between two points in meters.
     */
    protected function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if an IP address is within a CIDR subnet.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
