<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            // Core identification
            'id' => $this->resource->id,
            'user_id' => $this->resource->user_id,
            'employee_number' => $this->resource->employee_number,

            // Profile photo
            'profile_photo_url' => $this->resource->getProfilePhoto()?->getUrl(),

            // Personal info
            'first_name' => $this->resource->first_name,
            'middle_name' => $this->resource->middle_name,
            'last_name' => $this->resource->last_name,
            'suffix' => $this->resource->suffix,
            'full_name' => $this->resource->full_name,
            'initials' => $this->resource->initials,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'date_of_birth' => $this->resource->date_of_birth?->format('Y-m-d'),
            'age' => $this->resource->age,
            'gender' => $this->resource->gender,
            'civil_status' => $this->resource->civil_status,
            'nationality' => $this->resource->nationality,
            'fathers_name' => $this->resource->fathers_name,
            'mothers_name' => $this->resource->mothers_name,

            // Government IDs
            'tin' => $this->resource->tin,
            'sss_number' => $this->resource->sss_number,
            'philhealth_number' => $this->resource->philhealth_number,
            'pagibig_number' => $this->resource->pagibig_number,
            'umid' => $this->resource->umid,
            'passport_number' => $this->resource->passport_number,
            'drivers_license' => $this->resource->drivers_license,
            'nbi_clearance' => $this->resource->nbi_clearance,
            'police_clearance' => $this->resource->police_clearance,
            'prc_license' => $this->resource->prc_license,

            // Employment details
            'employment_type' => $this->resource->employment_type?->value,
            'employment_type_label' => $this->resource->employment_type?->label(),
            'employment_status' => $this->resource->employment_status?->value,
            'employment_status_label' => $this->resource->employment_status?->label(),
            'hire_date' => $this->resource->hire_date?->format('Y-m-d'),
            'regularization_date' => $this->resource->regularization_date?->format('Y-m-d'),
            'termination_date' => $this->resource->termination_date?->format('Y-m-d'),
            'years_of_service' => $this->resource->years_of_service,
            'basic_salary' => $this->resource->basic_salary,
            'pay_frequency' => $this->resource->pay_frequency,

            // Relationships
            'department_id' => $this->resource->department_id,
            'department' => $this->when(
                $this->resource->relationLoaded('department') && $this->resource->department,
                fn () => [
                    'id' => $this->resource->department->id,
                    'name' => $this->resource->department->name,
                    'code' => $this->resource->department->code,
                ]
            ),
            'position_id' => $this->resource->position_id,
            'position' => $this->when(
                $this->resource->relationLoaded('position') && $this->resource->position,
                fn () => [
                    'id' => $this->resource->position->id,
                    'title' => $this->resource->position->title,
                    'code' => $this->resource->position->code,
                ]
            ),
            'work_location_id' => $this->resource->work_location_id,
            'work_location' => $this->when(
                $this->resource->relationLoaded('workLocation') && $this->resource->workLocation,
                fn () => [
                    'id' => $this->resource->workLocation->id,
                    'name' => $this->resource->workLocation->name,
                    'code' => $this->resource->workLocation->code,
                    'city' => $this->resource->workLocation->city,
                ]
            ),
            'supervisor_id' => $this->resource->supervisor_id,
            'supervisor' => $this->when(
                $this->resource->relationLoaded('supervisor') && $this->resource->supervisor,
                fn () => [
                    'id' => $this->resource->supervisor->id,
                    'full_name' => $this->resource->supervisor->full_name,
                    'employee_number' => $this->resource->supervisor->employee_number,
                ]
            ),

            // JSON fields
            'address' => $this->resource->address,
            'emergency_contact' => $this->resource->emergency_contact,
            'education' => $this->resource->education,
            'work_history' => $this->resource->work_history,

            // Timestamps
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
