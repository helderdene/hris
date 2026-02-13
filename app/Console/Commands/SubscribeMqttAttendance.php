<?php

namespace App\Console\Commands;

use App\Events\AttendanceLogReceived;
use App\Services\Attendance\AttendanceLogProcessor;
use App\Services\Attendance\HeartbeatProcessor;
use App\Services\Attendance\MqttMessageParser;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

class SubscribeMqttAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe-attendance
                            {--topic=mqtt/face/+/Rec : The MQTT topic pattern to subscribe to}
                            {--heartbeat-topic=mqtt/face/heartbeat : The MQTT heartbeat topic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to MQTT attendance topics from biometric devices';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = false;

    /**
     * Track if we should continue running.
     */
    private bool $shouldRun = true;

    /**
     * Execute the console command.
     */
    public function handle(
        MqttMessageParser $parser,
        AttendanceLogProcessor $processor,
        HeartbeatProcessor $heartbeatProcessor
    ): int {
        $topic = $this->option('topic');
        $heartbeatTopic = $this->option('heartbeat-topic');

        $this->info('Starting MQTT attendance subscriber...');
        $this->info("Subscribing to topic: {$topic}");
        $this->info("Subscribing to heartbeat topic: {$heartbeatTopic}");

        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->shutdown());
            pcntl_signal(SIGINT, fn () => $this->shutdown());
        }

        try {
            $mqtt = MQTT::connection();

            $this->info('Connected to MQTT broker');

            $mqtt->subscribe(
                $topic,
                function (string $topic, string $message) use ($parser, $processor) {
                    $this->processMessage($topic, $message, $parser, $processor);
                },
                MqttClient::QOS_AT_LEAST_ONCE
            );

            $mqtt->subscribe(
                $heartbeatTopic,
                function (string $topic, string $message) use ($heartbeatProcessor) {
                    $this->processHeartbeat($topic, $message, $heartbeatProcessor);
                },
                MqttClient::QOS_AT_MOST_ONCE
            );

            $this->info('Subscribed successfully. Waiting for messages...');

            // Run the event loop
            while ($this->shouldRun) {
                $mqtt->loopOnce(100);

                // Check for signals
                if (extension_loaded('pcntl')) {
                    pcntl_signal_dispatch();
                }
            }

            $mqtt->disconnect();
            $this->info('Disconnected from MQTT broker');

        } catch (\Exception $e) {
            $this->error('MQTT error: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Process an incoming MQTT message.
     */
    private function processMessage(
        string $topic,
        string $message,
        MqttMessageParser $parser,
        AttendanceLogProcessor $processor
    ): void {
        $this->line("Received message on topic: {$topic}");

        try {
            $data = $parser->parse($topic, $message);

            if ($data === null) {
                $this->warn('Failed to parse message');

                return;
            }

            $this->line("Parsed data for employee: {$data->employeeCode}, device: {$data->deviceIdentifier}");

            $log = $processor->process($data);

            if ($log === null) {
                $this->warn("Failed to process attendance log for device: {$data->deviceIdentifier}");

                return;
            }

            $this->info("Created attendance log #{$log->id} for employee: {$data->employeeCode}");

            // Broadcast the new log for real-time dashboard updates
            if (class_exists(AttendanceLogReceived::class)) {
                event(new AttendanceLogReceived($log));
            }
        } catch (\Throwable $e) {
            $this->error("Error processing message: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Process an incoming MQTT heartbeat message.
     */
    private function processHeartbeat(
        string $topic,
        string $message,
        HeartbeatProcessor $heartbeatProcessor
    ): void {
        $this->line("Received heartbeat on topic: {$topic}");

        try {
            $heartbeatProcessor->process($message);
        } catch (\Throwable $e) {
            $this->error("Error processing heartbeat: {$e->getMessage()}");
        }
    }

    /**
     * Handle shutdown signal.
     */
    private function shutdown(): void
    {
        $this->info('Received shutdown signal, stopping...');
        $this->shouldRun = false;
    }
}
