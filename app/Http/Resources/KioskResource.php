<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Kiosk $resource
 */
class KioskResource extends JsonResource
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
            'token' => $this->resource->token,
            'location' => $this->resource->location,
            'work_location_id' => $this->resource->work_location_id,
            'ip_whitelist' => $this->resource->ip_whitelist,
            'settings' => $this->resource->settings,
            'is_active' => $this->resource->is_active,
            'last_activity_at' => $this->resource->last_activity_at?->toISOString(),
            'last_activity_human' => $this->resource->last_activity_at?->diffForHumans(),
            'cooldown_minutes' => $this->resource->getCooldownMinutes(),
            'kiosk_url' => $this->getKioskUrl(),
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
     * Generate the kiosk terminal URL.
     */
    protected function getKioskUrl(): string
    {
        return url("/kiosk/{$this->resource->token}");
    }
}
