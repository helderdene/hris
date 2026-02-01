<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \Illuminate\Notifications\DatabaseNotification $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->data;

        return [
            'id' => $this->resource->id,
            'type' => $data['type'] ?? $this->getShortType(),
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? '',
            'is_read' => $this->resource->read_at !== null,
            'read_at' => $this->resource->read_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'time_ago' => $this->resource->created_at?->diffForHumans(),
            'url' => $data['url'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
        ];
    }

    /**
     * Get a shortened version of the notification type.
     */
    private function getShortType(): string
    {
        $type = $this->resource->type;

        // Extract the class name from the full namespace
        $parts = explode('\\', $type);

        return end($parts);
    }
}
