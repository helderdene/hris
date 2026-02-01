<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Position $resource
 */
class PositionResource extends JsonResource
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
            'title' => $this->resource->title,
            'code' => $this->resource->code,
            'description' => $this->resource->description,
            'salary_grade_id' => $this->resource->salary_grade_id,
            'salary_grade' => $this->when(
                $this->resource->relationLoaded('salaryGrade') && $this->resource->salaryGrade,
                fn () => [
                    'id' => $this->resource->salaryGrade->id,
                    'name' => $this->resource->salaryGrade->name,
                    'minimum_salary' => $this->resource->salaryGrade->minimum_salary,
                    'midpoint_salary' => $this->resource->salaryGrade->midpoint_salary,
                    'maximum_salary' => $this->resource->salaryGrade->maximum_salary,
                    'currency' => $this->resource->salaryGrade->currency,
                ]
            ),
            'job_level' => $this->resource->job_level?->value,
            'job_level_label' => $this->resource->job_level?->label(),
            'employment_type' => $this->resource->employment_type?->value,
            'employment_type_label' => $this->resource->employment_type?->label(),
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
