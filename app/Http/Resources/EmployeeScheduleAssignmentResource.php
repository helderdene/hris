<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EmployeeScheduleAssignment $resource
 */
class EmployeeScheduleAssignmentResource extends JsonResource
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
            'employee_id' => $this->resource->employee_id,
            'work_schedule_id' => $this->resource->work_schedule_id,
            'shift_name' => $this->resource->shift_name,
            'effective_date' => $this->resource->effective_date?->toDateString(),
            'end_date' => $this->resource->end_date?->toDateString(),
            'employee' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee,
                fn () => [
                    'id' => $this->resource->employee->id,
                    'first_name' => $this->resource->employee->first_name,
                    'last_name' => $this->resource->employee->last_name,
                    'employee_number' => $this->resource->employee->employee_number,
                ]
            ),
            'work_schedule' => $this->when(
                $this->resource->relationLoaded('workSchedule') && $this->resource->workSchedule,
                fn () => [
                    'id' => $this->resource->workSchedule->id,
                    'name' => $this->resource->workSchedule->name,
                    'code' => $this->resource->workSchedule->code,
                    'schedule_type' => $this->resource->workSchedule->schedule_type?->value,
                ]
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
