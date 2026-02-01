<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\BiometricDevice $resource
 */
class BiometricDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'device_identifier' => $this->resource->device_identifier,
            'work_location_id' => $this->resource->work_location_id,
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'last_seen_at' => $this->resource->last_seen_at?->toISOString(),
            'last_seen_human' => $this->formatLastSeen(),
            'connection_started_at' => $this->resource->connection_started_at?->toISOString(),
            'is_active' => $this->resource->is_active,
            'uptime_seconds' => $this->resource->uptime_seconds,
            'uptime_human' => $this->resource->uptime_human,
            'work_location' => $this->whenLoaded('workLocation', fn () => [
                'id' => $this->resource->workLocation->id,
                'name' => $this->resource->workLocation->name,
                'code' => $this->resource->workLocation->code,
            ]),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format the last seen timestamp as human-readable relative time.
     */
    protected function formatLastSeen(): ?string
    {
        if ($this->resource->last_seen_at === null) {
            return null;
        }

        return $this->resource->last_seen_at->diffForHumans();
    }
}
