<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for calendar entries.
 *
 * @property-read \App\Models\TrainingSession $resource
 */
class TrainingCalendarEntryResource extends JsonResource
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
            'title' => $this->resource->display_title,
            'start' => $this->resource->start_date->format('Y-m-d'),
            'end' => $this->resource->end_date->format('Y-m-d'),
            'start_time' => $this->resource->start_time?->format('H:i'),
            'end_time' => $this->resource->end_time?->format('H:i'),
            'status' => $this->resource->status->value,
            'status_color' => $this->resource->status->badgeColor(),
            'location' => $this->resource->location,
            'is_full' => $this->resource->is_full,
            'enrolled_count' => $this->resource->enrolled_count,
            'available_slots' => $this->resource->available_slots,
            'course' => $this->when(
                $this->resource->relationLoaded('course') && $this->resource->course,
                fn () => [
                    'id' => $this->resource->course->id,
                    'title' => $this->resource->course->title,
                    'code' => $this->resource->course->code,
                ]
            ),
        ];
    }
}
