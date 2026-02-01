<?php

namespace App\Services\Attendance;

use App\DataTransferObjects\AttendanceLogData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Parses and validates MQTT messages from biometric attendance devices.
 */
class MqttMessageParser
{
    /**
     * Parse an MQTT message into an AttendanceLogData object.
     *
     * @param  string  $topic  The MQTT topic (e.g., mqtt/face/2582493/Rec)
     * @param  string  $payload  The JSON message payload
     */
    public function parse(string $topic, string $payload): ?AttendanceLogData
    {
        $deviceIdentifier = $this->extractDeviceIdentifier($topic);

        if ($deviceIdentifier === null) {
            Log::warning('Failed to extract device identifier from MQTT topic', [
                'topic' => $topic,
            ]);

            return null;
        }

        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to decode MQTT payload as JSON', [
                'topic' => $topic,
                'error' => json_last_error_msg(),
            ]);

            return null;
        }

        if (! $this->isValidPayload($data)) {
            Log::warning('Invalid MQTT payload structure', [
                'topic' => $topic,
                'operator' => $data['operator'] ?? 'missing',
            ]);

            return null;
        }

        $info = $data['info'];

        return new AttendanceLogData(
            deviceIdentifier: $deviceIdentifier,
            devicePersonId: (string) ($info['personId'] ?? ''),
            deviceRecordId: (string) ($info['RecordID'] ?? ''),
            employeeCode: (string) ($info['customId'] ?? ''),
            confidence: $this->parseConfidence($info['similarity1'] ?? '0'),
            verifyStatus: (string) ($info['VerifyStatus'] ?? ''),
            loggedAt: $this->parseTimestamp($info['time'] ?? ''),
            direction: $this->normalizeDirection($info['direction'] ?? null),
            personName: $info['personName'] ?? $info['persionName'] ?? null,
            capturedPhoto: $info['pic'] ?? null,
            rawPayload: $data,
        );
    }

    /**
     * Extract device identifier from MQTT topic.
     *
     * Topic pattern: mqtt/face/{device_id}/Rec
     */
    private function extractDeviceIdentifier(string $topic): ?string
    {
        if (preg_match('/^mqtt\/face\/(\d+)\/Rec$/i', $topic, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate the MQTT payload structure.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function isValidPayload(?array $data): bool
    {
        if ($data === null) {
            return false;
        }

        if (($data['operator'] ?? '') !== 'RecPush') {
            return false;
        }

        if (! isset($data['info']) || ! is_array($data['info'])) {
            return false;
        }

        $info = $data['info'];

        $requiredFields = ['customId', 'personId', 'RecordID', 'time'];

        foreach ($requiredFields as $field) {
            if (! isset($info[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse confidence score from device format.
     */
    private function parseConfidence(string $value): float
    {
        $confidence = (float) $value;

        return round(min(max($confidence, 0), 100), 2);
    }

    /**
     * Parse timestamp from device format.
     */
    private function parseTimestamp(string $value): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return Carbon::now();
        }
    }

    /**
     * Normalize direction value.
     */
    private function normalizeDirection(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'in', 'entry' => 'in',
            'out', 'exit' => 'out',
            default => $normalized,
        };
    }
}
