<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\SalaryGrade $resource
 */
class SalaryGradeResource extends JsonResource
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
            'minimum_salary' => $this->resource->minimum_salary,
            'midpoint_salary' => $this->resource->midpoint_salary,
            'maximum_salary' => $this->resource->maximum_salary,
            'currency' => $this->resource->currency,
            'status' => $this->resource->status,
            'steps' => $this->when(
                $this->resource->relationLoaded('steps'),
                fn () => SalaryStepResource::collection($this->resource->steps)
            ),
            'positions_count' => $this->when(
                $this->resource->relationLoaded('positions'),
                fn () => $this->resource->positions->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
