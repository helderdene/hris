<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\SalaryStep $resource
 */
class SalaryStepResource extends JsonResource
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
            'salary_grade_id' => $this->resource->salary_grade_id,
            'step_number' => $this->resource->step_number,
            'amount' => $this->resource->amount,
            'effective_date' => $this->resource->effective_date?->toDateString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
