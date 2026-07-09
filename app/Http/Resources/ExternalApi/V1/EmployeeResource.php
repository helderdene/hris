<?php

namespace App\Http\Resources\ExternalApi\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * External API resource for single employee detail.
 *
 * Includes additional fields beyond the list resource, but still excludes
 * sensitive data (salary, government IDs, address, emergency contacts).
 *
 * @property-read \App\Models\Employee $resource
 */
class EmployeeResource extends JsonResource
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
            'date_of_birth' => $this->resource->date_of_birth?->toDateString(),
            'gender' => $this->resource->gender,
            'employment_status' => $this->resource->employment_status?->value,
            'employment_type' => $this->resource->employment_type?->value,
            'hire_date' => $this->resource->hire_date?->toDateString(),
            'regularization_date' => $this->resource->regularization_date?->toDateString(),
            'termination_date' => $this->resource->termination_date?->toDateString(),
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
            'work_location' => $this->when(
                $this->resource->relationLoaded('workLocation') && $this->resource->workLocation,
                fn () => [
                    'id' => $this->resource->workLocation->id,
                    'name' => $this->resource->workLocation->name,
                ]
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
