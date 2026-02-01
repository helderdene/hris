<?php

namespace App\Events;

use App\Models\AttendanceLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for new attendance log entries.
 *
 * Uses ShouldBroadcastNow to send immediately without queuing,
 * ensuring real-time dashboard updates.
 */
class AttendanceLogReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AttendanceLog $log
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Get tenant from the application container (set by AttendanceLogProcessor)
        $tenant = tenant();

        if ($tenant === null) {
            return [];
        }

        return [
            new PrivateChannel("tenant.{$tenant->id}.attendance"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'log.received';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->log->id,
            'employee_id' => $this->log->employee_id,
            'employee_name' => $this->log->employee?->full_name ?? $this->log->person_name,
            'employee_code' => $this->log->employee_code,
            'confidence' => (float) $this->log->confidence,
            'logged_at' => $this->log->logged_at->toISOString(),
            'device_name' => $this->log->biometricDevice->name,
            'direction' => $this->log->direction,
        ];
    }
}
