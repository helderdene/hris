<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Competency $resource
 */
class CompetencyResource extends JsonResource
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
            'description' => $this->resource->description,
            'category' => $this->resource->category?->value,
            'category_label' => $this->resource->category?->label(),
            'is_active' => $this->resource->is_active,
            'position_competencies_count' => $this->when(
                isset($this->resource->position_competencies_count),
                fn () => $this->resource->position_competencies_count
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
