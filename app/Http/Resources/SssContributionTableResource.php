<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\SssContributionTable $resource
 */
class SssContributionTableResource extends JsonResource
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
            'employee_rate' => (float) $this->resource->employee_rate,
            'employer_rate' => (float) $this->resource->employer_rate,
            'is_active' => $this->resource->is_active,
            'brackets' => $this->when(
                $this->resource->relationLoaded('brackets'),
                fn () => SssContributionBracketResource::collection($this->resource->brackets)
            ),
            'brackets_count' => $this->when(
                $this->resource->relationLoaded('brackets'),
                fn () => $this->resource->brackets->count()
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
