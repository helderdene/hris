<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use App\Models\DeviceSyncLog;
use App\Models\Document;
use App\Models\Employee;
use App\Services\Mqtt\MqttPublisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for building and sending commands to biometric devices.
 *
 * Handles the business logic of constructing device-specific payloads
 * and logging sync operations.
 */
class DeviceCommandService
{
    public function __construct(
        protected MqttPublisher $mqttPublisher
    ) {}

    /**
     * Send an EditPerson command to sync an employee's data to a device.
     *
     * Creates a sync log and publishes the MQTT message.
     */
    public function editPerson(BiometricDevice $device, Employee $employee): DeviceSyncLog
    {
        $profilePhoto = $employee->getProfilePhoto();
        $payload = $this->buildEditPersonPayload($employee, $profilePhoto);

        $messageId = $this->mqttPublisher->publishToDevice($device, $payload);

        return DeviceSyncLog::create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'operation' => DeviceSyncLog::OPERATION_EDIT_PERSON,
            'message_id' => $messageId,
            'status' => DeviceSyncLog::STATUS_SENT,
            'request_payload' => $this->sanitizePayloadForLogging($payload),
            'sent_at' => now(),
        ]);
    }

    /**
     * Build the EditPerson payload for the MQTT message.
     *
     * @return array<string, mixed>
     */
    protected function buildEditPersonPayload(Employee $employee, ?Document $profilePhoto): array
    {
        $payload = [
            'operator' => 'EditPerson',
            'info' => [
                'customId' => $employee->employee_number,
                'name' => $employee->full_name,
            ],
        ];

        if ($profilePhoto !== null) {
            $base64Photo = $this->encodePhotoAsBase64($profilePhoto);

            if ($base64Photo !== null) {
                $payload['info']['pic'] = $base64Photo;
            }
        }

        return $payload;
    }

    /**
     * Encode a document's file as a base64 data URI.
     */
    protected function encodePhotoAsBase64(Document $document): ?string
    {
        try {
            $disk = Storage::disk('tenant');
            $path = $document->file_path;

            if (! $disk->exists($path)) {
                Log::warning('Profile photo file not found', [
                    'document_id' => $document->id,
                    'file_path' => $path,
                ]);

                return null;
            }

            $contents = $disk->get($path);
            $mimeType = $document->mime_type ?? 'image/jpeg';
            $base64 = base64_encode($contents);

            return "data:{$mimeType};base64,{$base64}";
        } catch (\Throwable $e) {
            Log::error('Failed to encode profile photo as base64', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sanitize the payload for logging (remove large base64 data).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayloadForLogging(array $payload): array
    {
        $sanitized = $payload;

        if (isset($sanitized['info']['pic'])) {
            $picLength = strlen($sanitized['info']['pic']);
            $sanitized['info']['pic'] = "[base64 data, {$picLength} bytes]";
        }

        return $sanitized;
    }
}
