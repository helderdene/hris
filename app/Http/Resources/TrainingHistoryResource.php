<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\TrainingEnrollment $resource
 */
class TrainingHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->resource->session;
        $course = $session?->course;

        return [
            'id' => $this->resource->id,
            'reference_number' => $this->resource->reference_number,

            // Employee info
            'employee' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee,
                fn () => [
                    'id' => $this->resource->employee->id,
                    'full_name' => $this->resource->employee->full_name,
                    'employee_number' => $this->resource->employee->employee_number,
                    'department' => $this->resource->employee->department?->name,
                    'position' => $this->resource->employee->position?->name,
                ]
            ),

            // Course info
            'course' => $this->when($course, fn () => [
                'id' => $course->id,
                'title' => $course->title,
                'code' => $course->code,
                'provider_name' => $course->provider_name,
                'provider_type' => $course->provider_type?->value,
                'duration_hours' => $course->duration_hours,
                'duration_days' => $course->duration_days,
                'formatted_duration' => $course->formatted_duration,
            ]),

            // Session info
            'session' => $this->when($session, fn () => [
                'id' => $session->id,
                'title' => $session->display_title,
                'start_date' => $session->start_date?->toDateString(),
                'end_date' => $session->end_date?->toDateString(),
                'date_range' => $session->date_range,
                'start_time' => $session->start_time ? Carbon::parse($session->start_time)->format('H:i') : null,
                'end_time' => $session->end_time ? Carbon::parse($session->end_time)->format('H:i') : null,
                'time_range' => $session->time_range,
                'location' => $session->location,
                'virtual_link' => $session->virtual_link,
                'status' => $session->status?->value,
                'status_label' => $session->status?->label(),
            ]),

            // Trainer info
            'trainer' => $this->when(
                $session && $session->relationLoaded('instructor') && $session->instructor,
                fn () => [
                    'id' => $session->instructor->id,
                    'full_name' => $session->instructor->full_name,
                ]
            ),

            // Enrollment status
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->badgeColor(),

            // Attendance
            'enrolled_at' => $this->resource->enrolled_at?->toISOString(),
            'attended_at' => $this->resource->attended_at?->toISOString(),

            // Assessment
            'assessment_score' => $this->resource->assessment_score,
            'completion_status' => $this->resource->completion_status?->value,
            'completion_status_label' => $this->resource->completion_status?->label(),
            'completion_status_color' => $this->resource->completion_status?->badgeColor(),
            'is_completed' => $this->resource->is_completed,

            // Certificate
            'certificate_number' => $this->resource->certificate_number,
            'certificate_issued_at' => $this->resource->certificate_issued_at?->toDateString(),
            'has_certificate' => $this->resource->has_certificate,

            // Notes
            'notes' => $this->resource->notes,

            // Timestamps
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
