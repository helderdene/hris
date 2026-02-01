<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\TrainingSession $resource
 */
class TrainingSessionResource extends JsonResource
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
            'course_id' => $this->resource->course_id,
            'title' => $this->resource->title,
            'display_title' => $this->resource->display_title,
            'start_date' => $this->resource->start_date->format('Y-m-d'),
            'end_date' => $this->resource->end_date->format('Y-m-d'),
            'date_range' => $this->resource->date_range,
            'start_time' => $this->resource->start_time?->format('H:i'),
            'end_time' => $this->resource->end_time?->format('H:i'),
            'time_range' => $this->resource->time_range,
            'location' => $this->resource->location,
            'virtual_link' => $this->resource->virtual_link,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->badgeColor(),
            'max_participants' => $this->resource->max_participants,
            'effective_max_participants' => $this->resource->effective_max_participants,
            'enrolled_count' => $this->resource->enrolled_count,
            'available_slots' => $this->resource->available_slots,
            'is_full' => $this->resource->is_full,
            'notes' => $this->resource->notes,
            'instructor_employee_id' => $this->resource->instructor_employee_id,
            'instructor' => $this->when(
                $this->resource->relationLoaded('instructor') && $this->resource->instructor,
                fn () => [
                    'id' => $this->resource->instructor->id,
                    'full_name' => $this->resource->instructor->full_name,
                ]
            ),
            'course' => $this->when(
                $this->resource->relationLoaded('course') && $this->resource->course,
                fn () => [
                    'id' => $this->resource->course->id,
                    'title' => $this->resource->course->title,
                    'code' => $this->resource->course->code,
                    'delivery_method' => $this->resource->course->delivery_method?->value,
                    'delivery_method_label' => $this->resource->course->delivery_method?->label(),
                    'level' => $this->resource->course->level?->value,
                    'level_label' => $this->resource->course->level?->label(),
                    'duration_hours' => $this->resource->course->duration_hours,
                    'formatted_duration' => $this->resource->course->formatted_duration,
                ]
            ),
            'creator' => $this->when(
                $this->resource->relationLoaded('creator') && $this->resource->creator,
                fn () => [
                    'id' => $this->resource->creator->id,
                    'full_name' => $this->resource->creator->full_name,
                ]
            ),
            'enrollments' => $this->when(
                $this->resource->relationLoaded('enrollments'),
                fn () => TrainingEnrollmentResource::collection($this->resource->enrollments)
            ),
            'waitlist' => $this->when(
                $this->resource->relationLoaded('waitlist'),
                fn () => TrainingWaitlistResource::collection($this->resource->waitlist)
            ),
            'waitlist_count' => $this->when(
                $this->resource->relationLoaded('waitlist'),
                fn () => $this->resource->waitlist->where('status', 'waiting')->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
