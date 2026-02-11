<?php

namespace App\Services\Mqtt;

use App\Models\BiometricDevice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

/**
 * Service for publishing MQTT messages to biometric devices.
 *
 * Handles the low-level MQTT communication with facial recognition devices.
 */
class MqttPublisher
{
    /**
     * Publish a command payload to a biometric device.
     *
     * @param  array<string, mixed>  $payload  The command payload to send
     * @return string The generated message ID
     */
    public function publishToDevice(BiometricDevice $device, array $payload): string
    {
        $topic = $this->buildTopic($device);
        $messageId = Str::uuid()->toString();
        $payload['messageId'] = $messageId;

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        Log::info('Publishing MQTT message to device', [
            'topic' => $topic,
            'message_id' => $messageId,
            'device_id' => $device->id,
            'device_identifier' => $device->device_identifier,
        ]);

        MQTT::connection()->publish($topic, $encodedPayload, 1);

        return $messageId;
    }

    /**
     * Build the MQTT topic for sending commands to a device.
     */
    protected function buildTopic(BiometricDevice $device): string
    {
        return "mqtt/face/{$device->device_identifier}";
    }
}
