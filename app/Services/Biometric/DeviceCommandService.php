<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use App\Models\DeviceSyncLog;
use App\Models\Document;
use App\Models\Employee;
use App\Services\DocumentStorageService;
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
     * Send an EditPerson command and wait for the device Ack before returning.
     *
     * Used for bulk sync to ensure each message is processed before the next.
     */
    public function editPersonAndWaitForAck(BiometricDevice $device, Employee $employee): DeviceSyncLog
    {
        $profilePhoto = $employee->getProfilePhoto();
        $payload = $this->buildEditPersonPayload($employee, $profilePhoto);

        $result = $this->mqttPublisher->publishAndWaitForAck($device, $payload);

        $status = DeviceSyncLog::STATUS_SENT;
        $responsePayload = null;

        if ($result['ack'] !== null) {
            $status = ($result['ack']['code'] ?? '') === '200'
                ? DeviceSyncLog::STATUS_ACKNOWLEDGED
                : DeviceSyncLog::STATUS_FAILED;
            $responsePayload = $result['ack'];
        }

        return DeviceSyncLog::create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'operation' => DeviceSyncLog::OPERATION_EDIT_PERSON,
            'message_id' => $result['message_id'],
            'status' => $status,
            'request_payload' => $this->sanitizePayloadForLogging($payload),
            'response_payload' => $responsePayload,
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
        $info = [
            'customId' => $employee->employee_number,
            'name' => $employee->full_name,
            'nation' => 1,
            'gender' => $this->mapGender($employee->gender),
            'birthday' => $employee->date_of_birth?->format('Y-m-d') ?? '',
            'address' => $this->formatAddress($employee->address),
            'idCard' => '',
            'tempCardType' => 0,
            'EffectNumber' => 3,
            'cardValidBegin' => $employee->hire_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'cardValidEnd' => '2099-12-31',
            'telnum1' => $employee->phone ?? '',
            'native' => $employee->nationality ?? '',
            'cardType2' => 0,
            'cardNum2' => '',
            'notes' => '',
            'personType' => 0,
            'cardType' => 0,
            'strategyInfo' => [
                'strategyNum' => 1,
                'strategyData' => [
                    [
                        'strategyType' => 6,
                        'startTime' => '00:00',
                        'endTime' => '23:59',
                    ],
                ],
            ],
        ];

        if ($profilePhoto !== null) {
            $base64Photo = $this->encodePhotoAsBase64($profilePhoto);

            if ($base64Photo !== null) {
                $info['pic'] = $base64Photo;
            }
        }

        return [
            'operator' => 'EditPerson',
            'info' => $info,
        ];
    }

    /**
     * Map employee gender to device gender code.
     *
     * Device uses: 0 = male, 1 = female.
     */
    protected function mapGender(?string $gender): int
    {
        return match ($gender) {
            'female' => 1,
            default => 0,
        };
    }

    /**
     * Format the employee address array into a single string.
     *
     * @param  array<string, string>|null  $address
     */
    protected function formatAddress(?array $address): string
    {
        if ($address === null) {
            return '';
        }

        return collect([
            $address['street'] ?? null,
            $address['barangay'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
            $address['postal_code'] ?? null,
        ])->filter()->implode(', ');
    }

    /**
     * Encode a document's file as a base64 data URI.
     */
    /**
     * Maximum image dimension (width or height) for device photos.
     */
    protected const MAX_PHOTO_DIMENSION = 500;

    /**
     * JPEG quality for device photos.
     */
    protected const PHOTO_JPEG_QUALITY = 85;

    protected function encodePhotoAsBase64(Document $document): ?string
    {
        try {
            $disk = Storage::disk(DocumentStorageService::getDiskName());

            // Use the latest version's file path if available, falling back to the document's file_path
            $latestVersion = $document->versions()->orderByDesc('version_number')->first();
            $path = $latestVersion?->file_path;

            if ($path === null || ! $disk->exists($path)) {
                $path = $document->file_path;
            }

            if (! $disk->exists($path)) {
                Log::warning('Profile photo file not found', [
                    'document_id' => $document->id,
                    'file_path' => $path,
                ]);

                return null;
            }

            $contents = $disk->get($path);
            $jpegContents = $this->optimizePhotoForDevice($contents);

            if ($jpegContents === null) {
                return null;
            }

            return 'data:image/jpeg;base64,'.base64_encode($jpegContents);
        } catch (\Throwable $e) {
            Log::error('Failed to encode profile photo as base64', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Optimize a photo for the biometric device by resizing and converting to JPEG.
     */
    protected function optimizePhotoForDevice(string $contents): ?string
    {
        $sourceImage = @imagecreatefromstring($contents);

        if ($sourceImage === false) {
            Log::warning('Failed to create image from file contents');

            return null;
        }

        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        // Resize if larger than max dimension
        if ($width > self::MAX_PHOTO_DIMENSION || $height > self::MAX_PHOTO_DIMENSION) {
            $ratio = min(self::MAX_PHOTO_DIMENSION / $width, self::MAX_PHOTO_DIMENSION / $height);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
            imagecopyresampled($resized, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($sourceImage);
            $sourceImage = $resized;
        }

        // Convert to baseline JPEG
        imageinterlace($sourceImage, 0);

        ob_start();
        imagejpeg($sourceImage, null, self::PHOTO_JPEG_QUALITY);
        $jpegContents = ob_get_clean();
        imagedestroy($sourceImage);

        return $jpegContents ?: null;
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
