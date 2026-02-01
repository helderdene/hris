<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Holiday $resource
 */
class HolidayResource extends JsonResource
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
            'date' => $this->resource->date?->toDateString(),
            'formatted_date' => $this->resource->date?->format('F j, Y'),
            'holiday_type' => $this->resource->holiday_type?->value,
            'holiday_type_label' => $this->resource->holiday_type?->label(),
            'description' => $this->resource->description,
            'is_national' => $this->resource->is_national,
            'year' => $this->resource->year,
            'work_location_id' => $this->resource->work_location_id,
            'work_location' => $this->when(
                $this->resource->relationLoaded('workLocation'),
                fn () => $this->resource->workLocation
                    ? new WorkLocationResource($this->resource->workLocation)
                    : null
            ),
            'scope_label' => $this->getScopeLabel(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Get a human-readable scope label for the holiday.
     */
    protected function getScopeLabel(): string
    {
        if ($this->resource->is_national) {
            return 'National';
        }

        if ($this->resource->relationLoaded('workLocation') && $this->resource->workLocation) {
            return $this->resource->workLocation->name;
        }

        return 'Location-specific';
    }
}
