<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PhilhealthContributionTable $resource
 */
class PhilhealthContributionTableResource extends JsonResource
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
            'contribution_rate' => (float) $this->resource->contribution_rate,
            'contribution_rate_percent' => (float) $this->resource->contribution_rate * 100,
            'employee_share_rate' => (float) $this->resource->employee_share_rate,
            'employer_share_rate' => (float) $this->resource->employer_share_rate,
            'salary_floor' => (float) $this->resource->salary_floor,
            'salary_ceiling' => (float) $this->resource->salary_ceiling,
            'min_contribution' => (float) $this->resource->min_contribution,
            'max_contribution' => (float) $this->resource->max_contribution,
            'is_active' => $this->resource->is_active,
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
