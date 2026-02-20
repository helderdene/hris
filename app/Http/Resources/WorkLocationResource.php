<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\WorkLocation $resource
 */
class WorkLocationResource extends JsonResource
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
            'code' => $this->resource->code,
            'address' => $this->resource->address,
            'city' => $this->resource->city,
            'region' => $this->resource->region,
            'country' => $this->resource->country,
            'postal_code' => $this->resource->postal_code,
            'location_type' => $this->resource->location_type?->value,
            'location_type_label' => $this->resource->location_type?->label(),
            'timezone' => $this->resource->timezone,
            'metadata' => $this->resource->metadata,
            'status' => $this->resource->status,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'geofence_radius' => $this->resource->geofence_radius,
            'ip_whitelist' => $this->resource->ip_whitelist,
            'location_check' => $this->resource->location_check,
            'self_service_clockin_enabled' => $this->resource->self_service_clockin_enabled,
            'formatted_address' => $this->formatAddress(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format the full address for display.
     */
    protected function formatAddress(): ?string
    {
        $parts = array_filter([
            $this->resource->address,
            $this->resource->city,
            $this->resource->region,
            $this->resource->postal_code,
            $this->resource->country,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }
}
