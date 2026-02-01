<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\TimeRecordPunch $resource
 */
class TimeRecordPunchResource extends JsonResource
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
            'daily_time_record_id' => $this->resource->daily_time_record_id,
            'attendance_log_id' => $this->resource->attendance_log_id,
            'punch_type' => $this->resource->punch_type?->value,
            'punch_type_label' => $this->resource->punch_type?->label(),
            'punched_at' => $this->resource->punched_at?->format('H:i'),
            'punched_at_full' => $this->resource->punched_at?->toISOString(),
            'is_valid' => $this->resource->is_valid,
            'invalidation_reason' => $this->resource->invalidation_reason,
            'attendance_log' => $this->when(
                $this->resource->relationLoaded('attendanceLog'),
                fn () => $this->resource->attendanceLog
                    ? new AttendanceLogResource($this->resource->attendanceLog)
                    : null
            ),
        ];
    }
}
