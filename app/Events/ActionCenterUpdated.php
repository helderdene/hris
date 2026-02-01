<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for Action Center Dashboard updates.
 *
 * Uses ShouldBroadcastNow to send immediately without queuing,
 * ensuring real-time dashboard updates for approval actions.
 */
class ActionCenterUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $action,
        public array $data = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $tenant = tenant();

        if ($tenant === null) {
            return [];
        }

        return [
            new PrivateChannel("tenant.{$tenant->id}.action-center"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'action-center.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
