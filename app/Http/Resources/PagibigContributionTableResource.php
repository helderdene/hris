<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PagibigContributionTable $resource
 */
class PagibigContributionTableResource extends JsonResource
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
            'effective_from' => $this->resource->effective_from->toDateString(),
            'effective_from_formatted' => $this->resource->effective_from->format('F j, Y'),
            'description' => $this->resource->description,
            'max_monthly_compensation' => (float) $this->resource->max_monthly_compensation,
            'is_active' => $this->resource->is_active,
            'tiers' => $this->when(
                $this->resource->relationLoaded('tiers'),
                fn () => PagibigContributionTierResource::collection($this->resource->tiers)
            ),
            'tiers_count' => $this->when(
                $this->resource->relationLoaded('tiers'),
                fn () => $this->resource->tiers->count()
            ),
            'created_by' => $this->resource->created_by,
            'creator_name' => $this->when(
                $this->resource->relationLoaded('creator'),
                fn () => $this->resource->creator?->name
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
