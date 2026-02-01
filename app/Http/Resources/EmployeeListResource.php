<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lighter payload resource for employee list views.
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
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->full_name,
            'initials' => $this->resource->initials,
            'profile_photo_url' => $this->resource->getProfilePhoto()?->getUrl(),
            'email' => $this->resource->email,
            'employment_type' => $this->resource->employment_type?->value,
            'employment_type_label' => $this->resource->employment_type?->label(),
            'employment_status' => $this->resource->employment_status?->value,
            'employment_status_label' => $this->resource->employment_status?->label(),
            'position' => $this->when(
                $this->resource->relationLoaded('position') && $this->resource->position,
                fn () => [
                    'id' => $this->resource->position->id,
                    'title' => $this->resource->position->title,
                ]
            ),
            'department' => $this->when(
                $this->resource->relationLoaded('department') && $this->resource->department,
                fn () => [
                    'id' => $this->resource->department->id,
                    'name' => $this->resource->department->name,
                ]
            ),
            'work_location' => $this->when(
                $this->resource->relationLoaded('workLocation') && $this->resource->workLocation,
                fn () => [
                    'id' => $this->resource->workLocation->id,
                    'name' => $this->resource->workLocation->name,
                ]
            ),
        ];
    }
}
