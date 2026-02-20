<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Visitor $resource
 */
class VisitorResource extends JsonResource
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
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->full_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'company' => $this->resource->company,
            'id_type' => $this->resource->id_type,
            'id_number' => $this->resource->id_number,
            'photo_path' => $this->resource->photo_path,
            'notes' => $this->resource->notes,
            'metadata' => $this->resource->metadata,
            'visits_count' => $this->when($this->resource->visits_count !== null, $this->resource->visits_count),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
