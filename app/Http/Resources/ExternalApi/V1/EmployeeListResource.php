<?php

namespace App\Http\Resources\ExternalApi\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * External API resource for employee list views.
 *
 * Exposes only non-sensitive employee data for ERP integrations.
 *
 * @property-read \App\Models\Employee $resource
 */
class EmployeeListResource extends JsonResource
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
            'employee_number' => $this->resource->employee_number,
            'first_name' => $this->resource->first_name,
            'middle_name' => $this->resource->middle_name,
            'last_name' => $this->resource->last_name,
            'suffix' => $this->resource->suffix,
            'full_name' => $this->resource->full_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'employment_status' => $this->resource->employment_status?->value,
            'employment_type' => $this->resource->employment_type?->value,
            'hire_date' => $this->resource->hire_date?->toDateString(),
            'department' => $this->when(
                $this->resource->relationLoaded('department') && $this->resource->department,
                fn () => [
                    'id' => $this->resource->department->id,
                    'name' => $this->resource->department->name,
                ]
            ),
            'position' => $this->when(
                $this->resource->relationLoaded('position') && $this->resource->position,
                fn () => [
                    'id' => $this->resource->position->id,
                    'title' => $this->resource->position->title,
                ]
            ),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
