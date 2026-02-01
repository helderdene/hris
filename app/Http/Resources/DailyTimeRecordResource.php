<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\DailyTimeRecord $resource
 */
class DailyTimeRecordResource extends JsonResource
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
            'date' => $this->resource->date?->toDateString(),
            'formatted_date' => $this->resource->date?->format('F j, Y'),
            'day_of_week' => $this->resource->date?->englishDayOfWeek,
            'work_schedule_id' => $this->resource->work_schedule_id,
            'shift_name' => $this->resource->shift_name,
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'first_in' => $this->resource->first_in?->format('H:i'),
            'first_in_full' => $this->resource->first_in?->toISOString(),
            'last_out' => $this->resource->last_out?->format('H:i'),
            'last_out_full' => $this->resource->last_out?->toISOString(),
            'total_work_minutes' => $this->resource->total_work_minutes,
            'total_work_hours' => $this->resource->total_work_hours,
            'total_break_minutes' => $this->resource->total_break_minutes,
            'late_minutes' => $this->resource->late_minutes,
            'late_formatted' => $this->resource->late_formatted,
            'undertime_minutes' => $this->resource->undertime_minutes,
            'undertime_formatted' => $this->resource->undertime_formatted,
            'overtime_minutes' => $this->resource->overtime_minutes,
            'overtime_formatted' => $this->resource->overtime_formatted,
            'overtime_approved' => $this->resource->overtime_approved,
            'night_diff_minutes' => $this->resource->night_diff_minutes,
            'remarks' => $this->resource->remarks,
            'needs_review' => $this->resource->needs_review,
            'review_reason' => $this->resource->review_reason,
            'computed_at' => $this->resource->computed_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => $this->resource->employee
                    ? new EmployeeListResource($this->resource->employee)
                    : null
            ),
            'work_schedule' => $this->when(
                $this->resource->relationLoaded('workSchedule'),
                fn () => $this->resource->workSchedule
                    ? new WorkScheduleResource($this->resource->workSchedule)
                    : null
            ),
            'punches' => $this->when(
                $this->resource->relationLoaded('punches'),
                fn () => TimeRecordPunchResource::collection($this->resource->punches)
            ),
        ];
    }
}
