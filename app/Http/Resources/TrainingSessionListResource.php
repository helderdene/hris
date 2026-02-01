<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for listing training sessions.
 *
 * @property-read \App\Models\TrainingSession $resource
 */
class TrainingSessionListResource extends JsonResource
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
            'display_title' => $this->resource->display_title,
            'start_date' => $this->resource->start_date->format('Y-m-d'),
            'end_date' => $this->resource->end_date->format('Y-m-d'),
            'date_range' => $this->resource->date_range,
            'time_range' => $this->resource->time_range,
            'location' => $this->resource->location,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->badgeColor(),
            'enrolled_count' => $this->resource->enrolled_count,
            'effective_max_participants' => $this->resource->effective_max_participants,
            'available_slots' => $this->resource->available_slots,
            'is_full' => $this->resource->is_full,
            'course' => $this->when(
                $this->resource->relationLoaded('course') && $this->resource->course,
                fn () => [
                    'id' => $this->resource->course->id,
                    'title' => $this->resource->course->title,
                    'code' => $this->resource->course->code,
                    'delivery_method_label' => $this->resource->course->delivery_method?->label(),
                ]
            ),
            'instructor' => $this->when(
                $this->resource->relationLoaded('instructor') && $this->resource->instructor,
                fn () => [
                    'id' => $this->resource->instructor->id,
                    'full_name' => $this->resource->instructor->full_name,
                ]
            ),
        ];
    }
}
