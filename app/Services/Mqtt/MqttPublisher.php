<?php

namespace App\Services\Mqtt;

use App\Models\BiometricDevice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

/**
 * Service for publishing MQTT messages to biometric devices.
 *
 * Handles the low-level MQTT communication with facial recognition devices.
 */
class MqttPublisher
{
    /**
     * Timeout in seconds to wait for device Ack.
     */
    protected const ACK_TIMEOUT_SECONDS = 15;

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

        $connection = MQTT::connection();
        $connection->publish($topic, $encodedPayload, 1);
        $connection->disconnect();

        return $messageId;
    }

    /**
     * Publish a command and wait for the device Ack before returning.
     *
     * Subscribes to the Ack topic, publishes the command, then loops
     * until the matching Ack arrives or the timeout is reached.
     *
     * @param  array<string, mixed>  $payload  The command payload to send
     * @return array{message_id: string, ack: array<string, mixed>|null}
     */
    public function publishAndWaitForAck(BiometricDevice $device, array $payload): array
    {
        $topic = $this->buildTopic($device);
        $ackTopic = $this->buildAckTopic($device);
        $messageId = Str::uuid()->toString();
        $payload['messageId'] = $messageId;

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        Log::info('Publishing MQTT message to device (waiting for Ack)', [
            'topic' => $topic,
            'message_id' => $messageId,
            'device_id' => $device->id,
            'device_identifier' => $device->device_identifier,
        ]);

        $ackReceived = null;
        $connection = MQTT::connection();

        // Subscribe to Ack topic first
        $connection->subscribe(
            $ackTopic,
            function (string $receivedTopic, string $message) use ($messageId, &$ackReceived, $connection) {
                $data = json_decode($message, true);

                if (($data['messageId'] ?? null) === $messageId) {
                    $ackReceived = $data;
                    Log::info('Device Ack received', [
                        'message_id' => $messageId,
                        'code' => $data['code'] ?? 'unknown',
                        'result' => $data['info']['result'] ?? 'unknown',
                    ]);
                    $connection->interrupt();
                }
            },
            MqttClient::QOS_AT_LEAST_ONCE
        );

        // Publish the command
        $connection->publish($topic, $encodedPayload, 1);

        // Wait for Ack with timeout
        $deadline = microtime(true) + self::ACK_TIMEOUT_SECONDS;

        while ($ackReceived === null && microtime(true) < $deadline) {
            $connection->loopOnce(100);
        }

        $connection->unsubscribe($ackTopic);
        $connection->disconnect();

        if ($ackReceived === null) {
            Log::warning('Device Ack timeout', [
                'message_id' => $messageId,
                'timeout_seconds' => self::ACK_TIMEOUT_SECONDS,
            ]);
        }

        return [
            'message_id' => $messageId,
            'ack' => $ackReceived,
        ];
    }

    /**
     * Build the MQTT topic for sending commands to a device.
     */
    protected function buildTopic(BiometricDevice $device): string
    {
        return "mqtt/face/{$device->device_identifier}";
    }

    /**
     * Build the MQTT topic for receiving Ack responses from a device.
     */
    protected function buildAckTopic(BiometricDevice $device): string
    {
        return "mqtt/face/{$device->device_identifier}/Ack";
    }
}
